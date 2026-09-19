<?php

namespace App\Triage;

use App\Entity\Experience;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\QuestionInterface;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ScoreQuestion;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Asks Jev three questions about every experience, in a single evaluation per experience:
 * what the person expects (Choice), how urgent it is (Score), whether it reports a bug (Noul).
 */
final class ExperienceTriage
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly LoggerInterface $logger,
        private readonly string $model = 'jev-latest',
    ) {
    }

    /**
     * @param iterable<Experience> $experiences
     *
     * @return array<int, TriageResult> Keyed by experience identifier; an experience Jev failed on is left out
     */
    public function triage(iterable $experiences): array
    {
        $questions = $this->createQuestions();

        // The HTTP requests are sent by invoke() and only awaited by asObject(), so they all run concurrently
        $pending = [];
        foreach ($experiences as $experience) {
            $pending[$experience->getId()] = $this->platform->invoke($this->model, new Evaluation($experience->getText(), $questions));
        }

        $results = [];
        foreach ($pending as $id => $deferred) {
            try {
                $answers = $deferred->asObject();
                \assert($answers instanceof Answers);

                $intention = $answers->getChoice('intention');
                $tokenUsage = $deferred->getMetadata()->get('token_usage');
                // Requests run concurrently: only the HTTP client knows how long each one really took
                $totalTime = $deferred->getRawResult()->getObject()->getInfo('total_time');

                $results[$id] = new TriageResult(
                    Intention::from($intention->getChoice()),
                    $intention->getConfidence(),
                    $answers->getScore('priority')->getScore(),
                    $answers->getNoul('bug')->getProbability(),
                    ($tokenUsage?->getPromptTokens() ?? 0) + ($tokenUsage?->getCompletionTokens() ?? 0),
                    is_numeric($totalTime) ? (int) round(1000 * $totalTime) : null,
                );
            } catch (\Throwable $e) {
                // One failure must not cost the answers already paid for: the caller decides what to do with the gaps
                $this->logger->warning('Jev could not triage experience {id}: {message}', ['id' => $id, 'message' => $e->getMessage(), 'exception' => $e]);
            }
        }

        return $results;
    }

    /**
     * @return array<string, QuestionInterface>
     */
    private function createQuestions(): array
    {
        $intentions = [];
        foreach (Intention::cases() as $intention) {
            $intentions[$intention->value] = $intention->description();
        }

        return [
            'intention' => new ChoiceQuestion('Qu’attend la personne qui a écrit ce message à un service public', $intentions),
            'priority' => new ScoreQuestion('À quel point cette demande est urgente à traiter', [
                'Aucune urgence : un remerciement ou une simple remarque',
                'Peut attendre : une gêne sans conséquence concrète',
                'À traiter vite : une démarche est bloquée depuis des semaines',
                'Urgence : la personne est privée de revenus, de papiers ou de soins',
            ]),
            'bug' => new NoulQuestion('Ce message signale un dysfonctionnement du site ou de l’application : bug, page d’erreur, formulaire bloqué, connexion impossible'),
        ];
    }
}
