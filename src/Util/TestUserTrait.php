<?php

namespace DkanTools\Util;

/**
 * Test User Trait.
 */
trait TestUserTrait
{
    /**
     * Private create user.
     */
    private function create($name, $pass, $roll, $mail)
    {
        $this->taskExecStack()
            ->stopOnFail()
            ->exec("dktl drush user:create $name --password=$pass --mail=$mail")
            ->exec("dktl drush user-add-role $roll $name")
            ->run();
    }

    /**
     * Protected create api user.
     */
    protected function apiUser()
    {
        $this->create("testuser", "2jqzOAnXS9mmcLasy", "api_user", "testuser@mail.com");
    }

    /**
     * Protected create editor.
     */
    protected function editorUser()
    {
        $this->create("testeditor", "testeditor", "administrator", "testeditor@mail.com");
    }
}
