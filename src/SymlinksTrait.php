<?php

namespace DkanTools;

use DkanTools\Util\Util;
use Symfony\Component\Filesystem\Filesystem;

trait SymlinksTrait
{
    private function addSymlinksToDrupalRoot()
    {
        $targetsAndLinks = [
            ['target' => 'src/site',    'link' => '/sites/default'],
            ['target' => 'src/modules', 'link' => '/modules/custom'],
            ['target' => 'src/themes',  'link' => '/themes/custom'],
            ['target' => 'src/schema',  'link' => '/schema'],
        ];
        foreach ($targetsAndLinks as $targetAndLink) {
            $this->docrootSymlink(
                $targetAndLink['target'],
                self::DRUPAL_FOLDER_NAME . $targetAndLink['link']
            );
        }
    }

    private function docrootSymlink($target, $link)
    {
        $project_dir = Util::getProjectDirectory();
        $target = $project_dir . "/{$target}";
        $link = $project_dir . "/{$link}";
        $link_parts = pathinfo($link);
        $link_dirname = $link_parts['dirname'];
        $target_path_relative_to_link = (new Filesystem())->makePathRelative($target, $link_dirname);

        if (!file_exists($target) || !file_exists(self::DRUPAL_FOLDER_NAME)) {
            $this->io()->warning(
                "Skipping linking $target. Folders $target and '" .
                self::DRUPAL_FOLDER_NAME."' must both be present to create link."
            );
            return;
        }

        $result = $this->taskFilesystemStack()->stopOnFail()
            ->remove($link)
            ->symlink($target_path_relative_to_link, $link)
            ->run();

        if ($result->getExitCode() != 0) {
            $this->io()->warning('Could not create link');
        } else {
            $this->io()->success("Symlinked $target to $link");
        }
        return $result;
    }
}
