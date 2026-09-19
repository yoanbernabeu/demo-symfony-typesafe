# DemoJev

Une application Symfony de démonstration pour **Jev**, le modèle « System One » de
[TypeSafe](https://docs.typesafe.ai/concepts/system-one), utilisé à travers
[Symfony AI](https://symfony.com/doc/current/ai/index.html).

Jev ne génère pas de texte. On lui donne un contenu et des questions typées, il
répond par des probabilités :

| Question | Ce qu'elle demande | Réponse |
|---|---|---|
| `NoulQuestion` | Oui ou non ? | Une probabilité entre 0 et 1 |
| `ChoiceQuestion` | Laquelle de ces options ? | L'option retenue, la probabilité de chacune, une confiance |
| `ScoreQuestion` | Quel niveau, sur une échelle ordonnée ? | Un score, la probabilité de chaque niveau, une confiance |

## Prérequis

- PHP 8.4 ou plus récent
- [Composer](https://getcomposer.org/)
- Une clé d'API TypeSafe
- En option, le [Symfony CLI](https://symfony.com/download)

## Installation

```bash
git clone <url-du-depot> DemoJev
cd DemoJev
composer install
```

Créez ensuite un fichier `.env.local` à la racine, avec votre clé :

```dotenv
TYPESAFE_API_KEY=votre-cle
```

Ce fichier est ignoré par git : la clé ne quitte pas votre machine.

## Premier essai

```bash
bin/console app:jev:test
```

La commande envoie un ticket de support à Jev avec trois questions (le service
concerné, le niveau de frustration, l'urgence) et affiche les réponses typées,
la consommation de tokens et la réponse brute de l'API.

Vous pouvez passer votre propre texte :

```bash
bin/console app:jev:test "Mon colis est arrivé ouvert et il manque la moitié de la commande."
```

## Le jeu de données

La démo travaille sur de vraies demandes adressées aux services publics français,
publiées en données ouvertes sur
[data.gouv.fr](https://www.data.gouv.fr/datasets/liste-des-experiences-partagees-par-les-usagers).
Seul le texte des demandes est conservé.

```bash
bin/console doctrine:migrations:migrate --no-interaction   # crée la base SQLite dans var/
bin/console app:dataset:download                           # télécharge le CSV (environ 260 Mo) dans var/dataset/
bin/console app:dataset:import                             # importe les demandes écrites depuis 2025
```

L'import peut être relancé sans risque : ce qui est déjà en base n'est pas réimporté.
Les options `--since=2026-01-01` et `--limit=500` permettent de n'en charger qu'une partie.

## Où regarder dans le code

- `src/Command/JevTestCommand.php` : un appel complet, des questions aux réponses.
- `src/Dataset/` : le téléchargement, la lecture en flux et l'import du jeu de données.
- `packages/ai-type-safe-platform/` : le bridge TypeSafe pour Symfony AI. Il est
  embarqué dans le dépôt, Composer le charge comme un paquet local.
- `config/services.yaml` : la déclaration de la plateforme. Le tag `ai.platform`
  la branche sur le profiler de l'AI Bundle.
