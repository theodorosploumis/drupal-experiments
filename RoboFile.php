<?php

declare(strict_types = 1);

use Robo\Result;
use Robo\ResultData;
use Robo\Tasks;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Process\Process;

/**
 * Robo commands.
 */
class RoboFile extends Tasks {

  public string $ddev_url = "drupal-actions";

  public string $drush_alias_prefix = "current";
  public string $mode = "dev";
  public string $code_testing_dir = "web/modules/custom";

  private array $var_dev_modules = [
    "devel",
    "stage_file_proxy",
    "field_ui",
    "views_ui",
      //  "shield",
      //  "reroute_email",
    "webprofiler", // keep always last
  ];

  private array $var_prd_modules = [
    "dynamic_page_cache",
    "big_pipe",
    "backup_migrate",
  ];

  public function __construct() {
    if (isset($_ENV['MODE'])) {
      $this->mode = strtolower($_ENV['MODE']);
    } else {
      $this->mode = "dev";
    }
  }

  /**
   * Get drush executable path.
   *
   * @return string
   */
  private function drush() {
    $path = $this->projectPath();
    return $path . "/vendor/bin/drush ";
  }

  /**
   * Get Drupal site path. Useful for multi-sites.
   *
   * @param string $site
   *
   * @return string
   */
  private function sitePath(string $site = "default") {
    return $this->projectPath() . '/web/sites/' . $site;
  }

  /**
   * Return current project root which contains the web folder.
   *
   * @return false|string|void
   */
  public function projectPath() {
    if (realpath("web") !== FALSE) {
      return getcwd();
    }
    $this->say("Cannot run robo from another root path. Aborting.");
    exit;
  }

  private function arrayImplode(array $array) {
    return implode(" ", $array);
  }

  /**
   * Checkout to the latest git tag.
   *
   * @return void
   */
  public function pullLatestTag() {
    $this->taskExec('git checkout master')->run();
    $this->taskExec('git pull')->run();
    $last_tag = $this->gitLastTag();
    $this->taskExec('git checkout ' . $last_tag)->run();
  }

  /**
   * Get latest git tag.
   *
   * @return string
   */
  private function gitLastTag() {
    return $this->taskExec("git describe --tags `git rev-list --tags --max-count=1`")
      ->printOutput(false)
      ->run()
      ->getMessage();
  }

  /**
   * Perform a Code sniffer test, and fix when applicable.
   *
   * @return \Robo\ResultData|null
   *   If there was an error a result data object is returned. Or null if
   *   successful.
   */
  public function phpcs(): ?ResultData {
    $standards = [
      'Drupal',
      'DrupalPractice',
      'PHPCompatibility',
    ];

    $commands = [
      'phpcbf',
      'phpcs',
    ];

    $directories = [
      'modules/custom',
      'themes/custom',
      'profiles/custom',
    ];

    $error_code = NULL;

    foreach ($directories as $directory) {
      foreach ($standards as $standard) {

        if ($standard !== "PHPCompatibility") {
          $extensions = "php,module,inc,install,test,profile,theme,js,css,yaml,yml,txt,md";
        } else {
          $extensions = "php,module,inc,install,test,profile,theme";
        }

        $arguments = "--standard=$standard -p --colors --extensions=" . $extensions;

        foreach ($commands as $command) {
          if (file_exists("web/" . $directory)) {
            $result = $this->_exec("cd web && ../vendor/bin/$command $directory $arguments");
            if ($error_code === NULL && !$result->wasSuccessful()) {
              $error_code = $result->getExitCode();
            }
          }
        }
      }
    }

    if ($error_code !== NULL) {
      return new ResultData($error_code, 'PHPCS found some issues');
    }
    return NULL;
  }

  public function modulesEnable(array $modules) {
    $list = $this->arrayImplode($modules);
    $this->modulesAction($list, "enable");
  }

  public function modulesDisable(array $modules) {
    $list = $this->arrayImplode($modules);
    $this->modulesAction($list, "disable");
  }

  /**
   * List, Enable or Disable modules.
   * See modules we exclude from config on "/docs/settings/global.settings.php: $settings['config_exclude_modules']".
   *
   * $modules = "field_ui views_ui devel stage_file_proxy";
   *
   * @param string $modules
   * @param string $status
   * @param string $site
   */
  public function modulesAction(string $modules = "", string $status = "", string $site = "default") {
    $drush = $this->drush();
    $command = "pml --no-core --status=enabled --type=module --format=table --fields=name,version";

    if ($status === "enable") {
      $command = "en -y ";
    }

    if ($status === "disable") {
      $command = "pmu -y ";
    }
    $this->taskExec($drush . $command . $modules)->run();
  }

  /**
   * Validate a mode.
   *
   * @param string $mode
   *
   * @return bool
   */
  private function validateMode(string $mode) {
    $modes = [
      "prd",
      "stg",
      "dev",
    ];

    if ($mode !== "" && !in_array($mode, $modes)) {
      print("Mode " . $mode . " is not valid. Can only use 'dev, stg, prd'.\n");
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Set site MODE (dev, stg, prd)
   *
   * @param string $mode
   * @param string $site
   *
   * @return void
   */
  public function siteSetMode(string $mode, string $site = "default") {
    $this->validateMode($mode);

    if ($mode === "prd") {
      $this->modulesEnable($this->var_prd_modules);
      $this->modulesDisable($this->var_dev_modules);
    } else {
      $this->modulesEnable($this->var_dev_modules);
      $this->modulesDisable($this->var_prd_modules);
      $this->configDisableCaches($site);
    }
  }

  /**
   * Several development tasks for a Dev site.
   *
   * @param string $site
   *
   * @return void
   */
  public function configDisableCaches(string $site = "default") {
    $drush = $this->drush();
//  $this->taskExec($drush . 'generate phpstorm-metadata -y')->run();
    $this->taskExec($drush . '-y config-set system.performance css.preprocess 0')->run();
    $this->taskExec($drush . '-y config-set system.performance js.preprocess 0')->run();
    $this->taskExec($drush . '-y config-set system.performance cache.page.max_age 0')->run();
  }

  /**
   * Update an existing site from new code and configuration files.
   *
   * @param string $mode
   * @param string $site
   */
  public function siteUpdate(string $mode, string $site = "default") {
    $this->validateMode($mode);
    $drush = $this->drush();

    $this->say("MODE:".$mode);
    $this->say("drush:".$drush);

    // Git pull master or latest tag.
    // Ensure we use only git Tags on Production
    if ($mode === "prd") {
      $this->pullLatestTag();
    } else {
      $this->taskGitStack()
        ->stopOnFail()
        ->pull("origin", "master")
        ->run();
    }

    // Run drush deploy before fetching new code to run hook_update_N
    $this->taskExec($drush . 'deploy -y')->run();

    // Preparation, remove folders
    $this->taskExec("rm -rf web/modules/contrib")->run();
    $this->taskExec("rm -rf web/themes/contrib")->run();
    $this->taskExec("rm -rf web/profiles/contrib")->run();

    // Composer install
    if ($mode === "prd") {
      $this->taskComposerInstall()->noDev()->optimizeAutoloader()->noInteraction()->run();
    } else {
      $this->taskComposerInstall()->optimizeAutoloader()->noInteraction()->run();
    }

    // Run drush deploy again
    $this->taskExec($drush . 'deploy -y')->run();

    // Enable/disable modules according to MODE
    $this->siteSetMode($mode, $site);

    // Clear caches
    $this->taskExec($drush . 'cache:rebuild -y')->run();

    $message = "--- INFO ---\n";
    $message .= "> Set mode: ".$mode.".\n";
    $message .= "> Project was updated (git, composer, config files).\n";
    $this->say($message);
  }

  /**
   * Install a fresh site from existing configuration files.
   *
   * @param string $site
   */
  public function siteInstall(string $site = "default") {
    $mode = $this->mode;
    $this->validateMode($mode);
    $drush = $this->drush();
    $path = $this->sitePath($site);

    // Preparation
    $this->taskExec("rm -rf web/modules/contrib")->run();
    $this->taskExec("rm -rf web/themes/contrib")->run();
    $this->taskExec("rm -rf web/profiles/contrib")->run();
    $this->taskExec("cp -f web/sites/default/default.services.yml ".$path."/services.yml")->run();

    // Composer install
    if ($mode === "prd") {
      $this->taskComposerInstall()->noDev()->optimizeAutoloader()->noInteraction()->run();
    } else {
      $this->taskComposerInstall()->optimizeAutoloader()->noInteraction()->run();
    }

    // Installation from existing config files
    $this->taskExec($drush . 'si --existing-config -y')->run();

    // Enable/disable modules according to MODE
    $this->siteSetMode($mode, $site);

    $message = "--- INFO ---\n";
    $message .= "> Set mode: ".$mode.".\n";
    $message .= "> Project was built from scratch.\n";
    $message .= "> Re-downloaded core and contrib from composer.\n";
    if ($mode !== "prd") {
      $message .= "> CSS/JS aggregation is turned off!\n";
    }
    // Print message
    $this->say($message);

    // Ready for development, open a login url
    $uli = "uli";
    // Inside DDEV
    if (isset($_ENV['IS_DDEV_PROJECT'])) {
      $uli = "uli --uri='https://".$this->ddev_url.".ddev.site'";
    }
    // Inside live Server
    if (isset($_ENV['DRUSH_OPTIONS_URI'])) {
      $uli = "uli --uri=". $_ENV['DRUSH_OPTIONS_URI'];
    }
    if (isset($_ENV['HOME']) && $_ENV['HOME'] === "/home/gitpod") {
      $links = $_ENV['DDEV_HOSTNAME'];
      $link_list = explode(",", $links);
      $last = end($link_list);
      $uli="uli --uri=" . str_replace("https://", "https://8080-", $last);
    }
    $this->taskExec($drush . $uli)->run();
  }

  /**
   * Run sql:dump for current site. This makes sense to stg and prd MODE.
   *
   * @param string $mode
   * @param string $site
   *
   * @return void
   */
  public function backupDatabase(string $mode, string $site = "default") {
    $this->validateMode($mode);
    $drush = $this->drush();

    // Inside ddev we do not need a backup path
    if (isset($_ENV['IS_DDEV_PROJECT'])) {
      $backup_path = "/var/www/html/";
    } else {
      $path_var = "var_backup_".$mode."_path";
      $backup_path = $this->{$path_var};
    }

    $tag = $this->gitLastTag();
    $filename = $backup_path . $mode ."-tag-".$tag.".sql";

    // drush sql:dump
    $this->taskExec($drush . "sql:dump --gzip --result-file=". $filename)->run();
  }

  /**
   * Generates a new Release (git tag).
   * This updates also CHANGELOG.md using package from marcocesarato/php-conventional-changelog.
   *
   * @param string $site
   * @param string $type (patch, minor, major)
   *
   * @return void
   */
  public function generateRelease(string $site = "default", string $type = "patch") {
    $current_branch = $this->taskExec("git rev-parse --abbrev-ref HEAD")
      ->printOutput(false)
      ->run()
      ->getMessage();

    if ($current_branch !== "master") {
      $message = "Can only generate CHANGELOG on branch master. Aborting.";
      $this->say($message);
      exit;
    }

    // Check latest tag with current Drupal version.
    // According to validations we create a major or minor release accordingly.
    $current_last_tag = $this->gitLastTag();
    $git_tag_semver = $this->getTagAsArray($current_last_tag);

    $core_version_command = "status --field=drupal-version";
    $drush = $this->drush();
    $drupal_version = $this->taskExec($drush . $core_version_command)
      ->printOutput(false)
      ->run()
      ->getMessage();

    $drupal_semver = $this->getTagAsArray($drupal_version);
    if ($drupal_semver["minor"] > $git_tag_semver["minor"]) {
      $type = "minor";
    }

    if ($drupal_semver["major"] > $git_tag_semver["major"]) {
      $type = "major";
    }

    // Update CHANGELOG.md, create a new git tag and push
    $this->taskExec("composer release:" . $type)->run();
    $last_tag = $this->gitLastTag();
    $commit = "SYSTEM-00: New " . $type . " release with tag " . $last_tag;

    $this->taskGitStack()
      ->stopOnFail()
      ->add("-A")
      ->commit($commit)
      ->pull('origin','master')
      ->push('origin','master')
      ->run();

    $this->taskExec("git push --tags")->run();
  }

  /**
   * Get minor and major version of a semver string like v9.3.0
   *
   * @param string $tag
   *
   * @return array
   */
  private function getTagAsArray(string $tag) {
    $current_tag = str_replace("v", "", $tag);
    $git_tag_versions = explode(".", $current_tag);

    return [
      "minor" => $git_tag_versions[1],
      "major" => $git_tag_versions[0],
    ];
  }

  /**
   * Testing commands for CI (GitHub Actions).
   * Based on RoboFile.php from https://github.com/Lullabot/drupal9ci,
   * https://github.com/fjgarlin/d9-lagoon and
   * https://github.com/juampynr/drupal8-github-actions
   */

  /**
   * Command to run unit tests.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobTestsUnit(): Result {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runUnitTests());
    return $collection->run();
  }

  /**
   * Command to generate a coverage report.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobCoverageReport(): Result {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runCoverageReport());
    return $collection->run();
  }

  /**
   * Command to check for Drupal's Coding Standards.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobCodingStandards(): Result {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runCodeSniffer());
    return $collection->run();
  }

  /**
   * Command to run Cypress tests.
   *
   * @return \Robo\Result
   *   The result tof the collection of tasks.
   */
  public function jobTestsCypress(): Result {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runCypressTests());
    return $collection->run();
  }

  /**
   * Install Drupal from existing config (CI command).
   *
   * @return void
   */
  public function CiInstallDrupal():void {
    $root = getenv('GITHUB_WORKSPACE');
    $web_root = $root . "/web";

    // Install Drupal
    $drush = $this->drush();
    $alias = ""; // "@current.ci"
//  --db-url=mysql://USER:PASSWORD@HOSTNAME:PORT/DATABASE
    $install_db = " --db-url=mysql://root:root@localhost:3306/drupal ";
    $install_user = " --account-pass=admin ";
    $install_uri = " --uri=http://localhost ";
    $install_root = " --root=" . $web_root . " ";
    $install_profile = " --existing-config ";

    // Initial debugging
    $this->taskExec($drush . $alias . $install_root . $install_uri . ' status -vvv')->run();

    $this->taskExec($drush . $alias . $install_root . $install_uri .
      $install_user . $install_db . $install_profile . ' site:install -vvv -y')->run();

    // Set site mode
    $this->siteSetMode("dev");
  }

  /**
   * Command to run behat tests.
   *
   * @return \Robo\Result
   *   The result tof the collection of tasks.
   */
  public function jobTestsBehat(): Result {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runBehatTests());
    return $collection->run();
  }

  /**
   * Runs Cypress tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runCypressTests(): array {
    $tasks = [];
    $tasks[] = $this->taskExec('npm install cypress --save-dev');
    $tasks[] = $this->taskExec('$(npm bin)/cypress run --browser chrome --config-file tests/cypress.json');
    return $tasks;
  }


  /**
   * Runs Behat tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runBehatTests(): array {
    $tasks = [];
    $behat_path = "";

    if (getenv('CI') === TRUE) {
      $behat_path = "tests/behat-ci.yml";
    }

    if (getenv('IS_DDEV_PROJECT') === "true") {
      $behat_path = "tests/behat.yml";
    }

    $tasks[] = $this->taskExec('vendor/bin/behat --verbose -c ' . $behat_path);
    return $tasks;
  }

  /**
   * Run unit tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runUnitTests(): array {
    $tasks = [];
    $tasks[] = $this->taskExecStack()
      ->exec('vendor/bin/phpunit --debug --verbose --testsuite=unit,kernel --log-junit=junit.xml ' . $this->code_testing_dir);
    return $tasks;
  }

  /**
   * Generates a code coverage report.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runCoverageReport(): array {
    $tasks = [];
    $tasks[] = $this->taskExecStack()
      ->exec('vendor/bin/phpunit --debug --verbose --coverage-html ../coverage --testsuite=unit,kernel ' . $this->code_testing_dir);
    return $tasks;
  }

  /**
   * Sets up and runs code sniffer.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runCodeSniffer(): array {
    $tasks = [];
    $tasks[] = $this->taskExecStack()
      ->exec('vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer');
    $tasks[] = $this->taskFilesystemStack()
      ->mkdir('artifacts/phpcs');
    $tasks[] = $this->taskExecStack()
      ->exec('vendor/bin/phpcs --standard=Drupal --report=junit --report-junit=artifacts/phpcs/phpcs.xml ' . $this->code_testing_dir)
      ->exec('vendor/bin/phpcs --standard=DrupalPractice --report=junit --report-junit=artifacts/phpcs/phpcs.xml ' . $this->code_testing_dir);
    return $tasks;
  }
}
