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

## L'interface

Une boîte de réception affiche les demandes importées : liste paginée, recherche dans le texte,
lecture d'une demande sans recharger la page.

```bash
bin/console tailwind:build   # compile le CSS (le binaire Tailwind est téléchargé au premier lancement)
symfony serve                # puis ouvrez http://127.0.0.1:8000
```

Avec `symfony serve`, le CSS est ensuite recompilé automatiquement à chaque modification.

Elle s'appuie sur [Symfony UX](https://ux.symfony.com/) : Turbo Frames pour la navigation, Stimulus
pour les deux comportements côté navigateur, Twig Components et le kit
[shadcn de UX Toolkit](https://ux.symfony.com/toolkit/kits/shadcn) pour les composants, Tailwind CSS
pour le style. Les composants du kit sont copiés dans `templates/components/` : ils font partie du
projet et se modifient librement. Pour en ajouter un :

```bash
bin/console ux:install dialog --kit shadcn
```

## La qualification par Jev

Chaque demande est envoyée à Jev avec trois questions, en un seul appel :

| Question | Type | Réponse |
|---|---|---|
| Qu'attend la personne ? | Choice | Une intention parmi six, avec une confiance |
| À quel point est-ce urgent ? | Score | Une priorité de 0 à 3 |
| Est-ce un bug du site à transmettre aux devs ? | Noul | Une probabilité |

Le traitement est asynchrone : les demandes partent dans une file Messenger par lots, un worker
les envoie à Jev et enregistre les réponses. La page **Qualification** suit l'avancement en direct
(demandes qualifiées, débit, latence de Jev, tokens, répartition par intention) et porte le bouton
de lancement. Avec `symfony serve`, le worker tourne déjà. Sinon :

```bash
bin/console messenger:consume async
```

Trois variables, à surcharger dans `.env.local`, cadrent le traitement :

| Variable | Défaut | Rôle |
|---|---|---|
| `APP_TRIAGE_SINCE` | `2026-01-01` | Seules les demandes écrites depuis ce jour sont envoyées à Jev |
| `APP_TRIAGE_LIMIT` | `1000` | Plafond du nombre total de demandes envoyées à Jev, pour maîtriser la facture. `0` retire le plafond |
| `APP_TRIAGE_BATCH_SIZE` | `25` | Nombre de demandes par message, envoyées à Jev en même temps |

En ligne de commande :

```bash
bin/console app:triage:start   # met les demandes en file, comme le bouton
bin/console app:triage:reset   # oublie les réponses de Jev, pour rejouer la qualification
```

Une fois qualifiées, les demandes se filtrent par intention, par « bugs à transmettre », et se
trient par urgence dans la boîte de réception.

## Où regarder dans le code
- `src/Command/JevTestCommand.php` : un appel complet, des questions aux réponses.
- `src/Controller/InboxController.php` et `templates/inbox/` : la boîte de réception.
- `src/Triage/` : les trois questions posées à Jev, le lancement et le suivi de la qualification.
- `src/Message/` et `src/MessageHandler/` : le traitement asynchrone d'un lot de demandes.
- `src/Twig/Components/TriageDashboard.php` : le tableau de bord, un Live Component qui s'actualise seul.
- `src/Dataset/` : le téléchargement, la lecture en flux et l'import du jeu de données.
- `packages/ai-type-safe-platform/` : le bridge TypeSafe pour Symfony AI. Il est
  embarqué dans le dépôt, Composer le charge comme un paquet local.
- `config/services.yaml` : la déclaration de la plateforme. Le tag `ai.platform`
  la branche sur le profiler de l'AI Bundle.
