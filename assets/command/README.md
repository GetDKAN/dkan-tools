## DKAN Tools Custom Commands

DKAN Tools (DKTL) is build on top of the Robo framework: https://robo.li/

DKTL allows projects to define their own commands.

To create a custom command create a new class inside of this project with a similar structure to the this one:

```
<?php
namespace DkanTools\Custom;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class CustomCommands extends \Robo\Tasks
{
    /**
     * Sample.
     */
    public function customSample()
    {
        $this->io()->comment("Hello World!!!");
    }
}
```

The critical parts of the example are:
1) The namespace
1) The extension of \Robo\Tasks
1) The name of the file for the class should match the class name. In this case the file name should be CustomCommands.php

Everything else is flexible: 
1) The class name
1) The function names
1) etc

Each function inside of the class will show up as an available DKTL command.