<?php

namespace App\Triage;

/**
 * What the person who wrote to a public service expects. The cases are the
 * options Jev chooses from, and the values stored in the database.
 */
enum Intention: string
{
    case Unblock = 'debloquer';
    case Information = 'information';
    case Contest = 'contester';
    case Reception = 'accueil';
    case Improvement = 'amelioration';
    case Thanks = 'remercier';

    public function label(): string
    {
        return match ($this) {
            self::Unblock => 'Débloquer un dossier',
            self::Information => 'Obtenir une information',
            self::Contest => 'Contester une décision',
            self::Reception => 'Se plaindre de l’accueil',
            self::Improvement => 'Proposer une amélioration',
            self::Thanks => 'Remercier',
        };
    }

    /**
     * What the option means, as explained to Jev.
     */
    public function description(): string
    {
        return match ($this) {
            self::Unblock => 'Débloquer un dossier : une démarche est en cours et n’avance plus',
            self::Information => 'Obtenir une information : elle ne sait pas quoi faire ou comment le faire',
            self::Contest => 'Contester une décision : un refus, une amende, un montant',
            self::Reception => 'Se plaindre de l’accueil : un agent, un guichet, un standard injoignable',
            self::Improvement => 'Proposer une amélioration du service',
            self::Thanks => 'Remercier pour un service qui a bien fonctionné',
        };
    }
}
