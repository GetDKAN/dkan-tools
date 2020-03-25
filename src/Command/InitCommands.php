<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;

class InitCommands extends \Robo\Tasks
{

    /**
     * Generates basic configuration for a DKAN project to work with CircleCI.
     */
    public function initCircleCI()
    {
        $dktl_dir = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();
        return $this->taskExec("cp -r {$dktl_dir}/assets/.circleci {$project_dir}")->run();
    }

    /**
     * Generates basic configuration for a DKAN project to work with ProboCI.
     */
    public function initProboCI()
    {
        $dktl_dir = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();
        $collection = $this->collectionBuilder();
        $collection->addTask($this->taskExec("cp -r {$dktl_dir}/assets/.probo.yml {$project_dir}"));
        $collection->addTask($this->taskExec("cp -r {$dktl_dir}/assets/settings.probo.php {$project_dir}/src/site"));
        return $collection->run();
    }
}
