# Drupal 9.x Demostration

## About

A simple Unami website to demonstrate Drupal 9.x CI with GitHub actions, ddev, git-hooks, gitpod etc.

## Development

- For most of the tasks bellow there is a Robo command available. Run `./vendor/bin/robo` to get the list of available commands.
- We use `.env` file for Database credentials and environment mode. See docs/settings/.env.example for reference.
- According to the env variable "MODE" we include automatically the related `MODE.settings.local.php` file.
- There are 3 available MODE options: `dev, stg, prd`.
- All files under `docs` folder are tracked by vcs. Do not edit them for local development.
- An additional local `settings.local.php` file can be added manually under `web/sites/default/` folder. This file is not tracked by vcs.
- Check `web/sites/default/settings.php` for all the above instructions.
- DDEV (https://ddev.readthedocs.io) was used for initial local development. Just run `ddev start && ddev robo site:install` and you will get a fresh copy of the website.
- When exporting configuration with `drush cex` there is a list of excluded modules. See `docs/global.settings.php:$settings['config_exclude_modules']`
- We use git as VCS.
- Branch `master` is the development main branch. There are no `dev` or `stage` branches.
- We use GitHub pull requests and feature branches.
- For every new branch you create add the Jira issue prefix (eg `PROJ-77`). Example `PROJ-77_basic_structure`.
- Add the Jira issue prefix (eg `PROJ-79`) on every git commit. Example: `PROJ-77: Create basic Drupal entity structure`.
There is a git hook to validate git commits.
- We try to use git conventional commits. See https://www.conventionalcommits.org.
  Add `fix, feat, docs, test, refactor` etc after the project Issue number. Example: `PROJ-85: feat, My new feature`
- We create CHANGELOG.md file from branch merges using https://github.com/marcocesarato/php-conventional-changelog.
We automate this process with the command `composer release:patch`
- All the database updates (hook_update_N), universal CSS/JS changes and global hooks should be done on module `custom/GLOBAL_CUSTOM_MODULE`.

## Installation

```shell
git clone git@github.com:theodorosploumis/drupal-actions.git
cd drupal-actions

# Requires: composer 2.x, PHP 8.0
composer install

cp .env.example .env
# Change db settings on .env
vim .env

# Clean site install
./vendor/bin/robo site:install

# Login array set user 1
./vendor/bin/drush uli
```

DDEV equivalent installation example:

```shell
git clone git@github.com:theodorosploumis/drupal-actions.git
cd drupal-actions
ddev start
ddev auth ssh
ddev robo site:install
ddev drush uli
```

## Testing

- We use `phpunit`, `cypress` and `behat` for testing.
- Run commands through Robo. See `robo:test-*` commands.
- With ddev and Cypress tests you can also use the dedicated ddev commands:

```shell
ddev cypress run --browser chrome --config-file tests/cypress.json
ddev cypress open --browser chrome --config-file tests/cypress.json
```

## Important notes

- Requires: Composer 2.x, php 8
