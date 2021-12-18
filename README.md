# Drupal 9.x with GutHub Actions

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
cp .env.example .env
ddev start
ddev auth ssh
ddev robo site:install
ddev drush uli
```

## Testing

- We use `phpunit`, `cypress` and `behat` for testing.
- Run commands through Robo. See `robo:test-*` commands.
- With ddev and Cypress tests you can also use the dedicated ddev commands:
```
ddev cypress-run --browser chrome --config-file tests/cypress.json
ddev cypress-open --browser chrome --config-file tests/cypress.json
```

## Important notes

- Requires: Composer 2.x, php 8
