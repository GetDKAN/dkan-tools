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
    private function createTestUsers()
    {
        $people = $this->getUsers();
        foreach ($people as $person) {
            $name = $person->name;
            $mail = $person->mail;
            $role = $person->role;
            $this->taskExecStack()
                ->stopOnFail()
                ->exec("dktl drush user:create $name --password=$name --mail=$mail")
                ->exec("dktl drush user-add-role $role $name")
                ->run();
        }
    }

    /**
     * Get user.
     */
    protected function userExists($name)
    {
        if ($this->taskExecStack()->stopOnFail()->exec("dktl drush user:information $name")->run()->wasSuccessful()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get user list.
     */
    protected function getUsers()
    {
        $dktlRoot = Util::getDktlDirectory();
        $list = file_exists("testUsers.json") ? "testUsers.json" : $dktlRoot . '/testUsers.json';
        $json = file_get_contents($list);
        $user = json_decode($json);
        return $user;
    }

    /**
     * Protected delete user.
     */
    public function deleteTestUsers()
    {
        $people = $this->getUsers();
        foreach ($people as $person) {
            $name = $person->name;
            $user = $this->userExists($name);
            if ($user) {
                $this->taskExecStack()
                    ->stopOnFail()
                    ->exec("dktl drush user:cancel --delete-content $name -y")
                    ->run();
            }
        }
    }
}
