<?php

namespace App\Command;

use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ScoreQuestion;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:jev:test', 'Sends a support ticket to TypeSafe Jev and dumps the typed answers')]
final class JevTestCommand
{
    public function __construct(
        private readonly PlatformInterface $platform,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The ticket to evaluate')] string $ticket = "Hi, I've been trying to connect my Stripe account for 3 days and it keeps failing. I'm losing sales. Please help ASAP.",
        #[Option('The model to use')] string $model = 'jev-latest',
    ): int {
        $result = $this->platform->invoke($model, new Evaluation($ticket, [
            'department' => new ChoiceQuestion('Which team should handle this', [
                'billing' => 'Payment or subscription issues',
                'technical' => 'Bugs or integration problems',
                'sales' => 'Pricing or account questions',
            ]),
            'frustration' => new ScoreQuestion('How frustrated the customer appears', [
                'Calm, just stating facts',
                'Frustrated but civil',
                'Very angry, strong language',
            ]),
            'is_urgent' => new NoulQuestion('The message conveys urgency or time-sensitivity'),
        ]));

        $answers = $result->asObject();
        \assert($answers instanceof Answers);

        $department = $answers->getChoice('department');
        $frustration = $answers->getScore('frustration');
        $urgent = $answers->getNoul('is_urgent');

        $io->title('Jev answers');
        $io->definitionList(
            ['department' => \sprintf('%s (confidence %.3f)', $department->getChoice(), $department->getConfidence())],
            ['  probabilities' => json_encode($department->getProbabilities())],
            ['frustration' => \sprintf('%.3f (confidence %.3f)', $frustration->getScore(), $frustration->getConfidence())],
            ['  legend' => json_encode($frustration->getLegend())],
            ['  probabilities' => json_encode($frustration->getProbabilities())],
            ['is_urgent' => \sprintf('%.3f → %s', $urgent->getProbability(), $urgent->isTrue() ? 'yes' : 'no')],
        );

        $tokenUsage = $result->getMetadata()->get('token_usage');
        $io->section('Token usage');
        $io->definitionList(
            ['model' => $tokenUsage?->getModel()],
            ['input tokens' => $tokenUsage?->getPromptTokens()],
            ['output tokens' => $tokenUsage?->getCompletionTokens()],
        );

        $io->section('Raw response');
        $io->writeln(json_encode($result->getRawResult()->getData(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
