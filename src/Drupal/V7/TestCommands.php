<?php

namespace DkanTools\Command;

namespace DkanTools\Drupal\V7;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class TestCommands extends \Robo\Tasks
{
    /**
     * Initialize test folders and install dependencies for running tests.
     *
     * Initialize test folders and install dependencies for running tests. This
     * command will run composer install, and create an "assets" folder under
     * "dkan/test" for output. Usually this command does not need to be run on
     * its own as all other test commands run it first.
     */
    public function testInit()
    {
        if (!file_exists('dkan/test/vendor')) {
            $this->io()->section('Installing test dependencies.');
            $this->taskExec('composer install --prefer-source --no-interaction')
                ->dir('dkan/test')
                ->run();
        }
        if (!file_exists('dkan/test/assets')) {
            $this->io()->section('Creating test subdirectories.');
            $this->_mkdir('dkan/test/assets/junit');
        }
    }

    /**
     * Runs DKAN core Behat tests.
     *
     * Runs DKAN core Behat tests. Pass any additional behat options as
     * arguments. For example:
     *
     * dktl test:behat --name="Datastore API"
     *
     * or
     *
     * dktl test:behat features/workflow.feature
     *
     * @param array $args  Arguments to append to behat command.
     */
    public function testBehat(array $args)
    {
        $this->testInit();
        $behatExec = $this->taskExec('bin/behat')
            ->dir('dkan/test')
            ->arg('--colors')
            ->arg('--suite=dkan')
            ->arg('--format=pretty')
            ->arg('--out=std')
            ->arg('--format=junit')
            ->arg('--out=assets/junit')
            ->arg('--config=behat.docker.yml');

        foreach ($args as $arg) {
            $behatExec->arg($arg);
        }
        return $behatExec->run();
    }

    /**
     * Runs DKAN core PhpUnit tests.
     *
     * Runs DKAN core PhpUnit tests. Pass any additional PhpUnit options as
     * arguments. For example:
     *
     * dktl test:phpunit --testsuite="DKAN Harvest Test Suite"
     *
     * @see https://phpunit.de/manual/6.5/en/textui.html
     *
     * @param array $args  Arguments to append to full phpunit command.
     */
    public function testPhpunit(array $args)
    {
        $this->testInit();
        $phpunitExec = $this->taskExec('bin/phpunit --verbose')
            ->dir('dkan/test')
            ->arg('--configuration=phpunit');

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }
        return $phpunitExec->run();
    }

    public function testCypress()
    {
        $proj_dir = Util::getProjectDirectory();
        $this->_exec("npm install cypress");
        $this->_exec("CYPRESS_baseUrl=http://web {$proj_dir}/node_modules/cypress/bin/cypress run");
    }

    private function getVendorCommand($binary_name) {
        $dktl_dir = Util::getDktlDirectory();
        return "{$dktl_dir}/vendor/bin/{$binary_name}";
    }

    /**
     * Proxy to phpcs.
     */
    public function phpcs(array $args) {
        $dktl_dir = Util::getDktlDirectory();

        $phpcs_command = $this->getVendorCommand("phpcs");

        $task = $this->taskExec("{$phpcs_command} --config-set installed_paths {$dktl_dir}/vendor/drupal/coder/coder_sniffer");
        $task->run();

        $task = $this->taskExec($phpcs_command);
        foreach ($args as $arg) {
            $task->arg($arg);
        }
        $task->run();
    }

    /**
     * Proxy to phpcbf.
     */
    public function phpcbf(array $args) {
        $phpcbf_command = $this->getVendorCommand("phpcbf");

        $task = $this->taskExec($phpcbf_command);
        foreach ($args as $arg) {
            $task->arg($arg);
        }
        $task->run();
    }

    /**
     * Preconfigured linting for paths inside of the repo.
     */
    public function testLint(array $paths) {
        $dktl_dir = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();

        $phpcs_command = $this->getVendorCommand("phpcs");

        $task = $this->taskExec("{$phpcs_command} --config-set installed_paths {$dktl_dir}/vendor/drupal/coder/coder_sniffer");
        $task->run();

        $task = $this->taskExec("{$phpcs_command} --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,info");

        foreach ($paths as $path) {
            $task->arg("{$project_dir}/{$path}");
        }

        $task->run();
    }

    /**
     * Preconfigured lint fixing for paths inside of the repo.
     */
    public function testLintFix(array $paths) {
        $project_dir = Util::getProjectDirectory();

        $phpcbf_command = $this->getVendorCommand("phpcbf");

        $task = $this->taskExec("{$phpcbf_command} --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,info");

        foreach ($paths as $path) {
            $task->arg("{$project_dir}/{$path}");
        }

        $task->run();
    }

    /**
     * Create QA users for each basic DKAN role.
     *
     * Running this command will create three users: sitemanager, editor, and
     * creator. They will be assigned the corresponding role and a password
     * equal to the username.
     *
     * @option $workflow Create workflow users as well.
     */
    public function testQaUsers($opts = ['workflow|w' => false]) {
        $users = [
            'sitemanager' => ['site manager'],
            'editor' => ['editor'],
            'creator' => ['content creator']
        ];
        if ($opts['workflow']) {
            if ($this->hasWorkflow()) {
                $users += [
                    'contributor' => ['content creator', 'Workflow Contributor'],
                    'moderator' => ['editor' , 'Workflow Moderator'],
                    'supervisor' => ['site manager', 'Workflow Supervisor']
                ];
            }
            else {
                throw new \Exception('Workflow QA users requested, but dkan_workflow_permissions not enbled.');
            }
        }
        $stack = $this->taskExecStack()->stopOnFail()->dir('docroot');
        foreach($users as $user => $roles) {
            // Add stack of drush commands to create users and assign roles.
            $stack->exec("drush ucrt $user --mail={$user}@example.com --password={$user}");
            foreach($roles as $role) {
                $stack->exec("drush urol '{$role}' --name={$user}");
            }
        }
        $result = $stack->run();
        return $result;
    }

    /**
     * Use Drush to check if dkan_workflow_permissions module is enabled.
     */
    private function hasWorkflow() {
        $result = $this->taskExec('drush php-eval')
            ->arg('echo module_exists("dkan_workflow_permissions");')
            ->dir('docroot')
            ->printOutput(FALSE)
            ->run();
        if ($result->getExitCode() == 0) {
            return $result->getMessage();
        }
        else {
          throw new \Exception('Drush command failed; aborting');
        }
    }
}
