<?php

namespace VersionPullRequester;

class ComposerJsonReplacer
{
    public function replaceVersionOfDependency(array $oldContent, string $dependency,
                                               string $dependencyNewVersion, ?string $onlyIfOldVersionEqualsTo = null): array
    {
        $newContent = $oldContent;

        $newContent['require'] = $this->replaceThroughDependencies($oldContent['require'], $dependency, $dependencyNewVersion, $onlyIfOldVersionEqualsTo);
        $newContent['require-dev'] = $this->replaceThroughDependencies($oldContent['require-dev'], $dependency, $dependencyNewVersion, $onlyIfOldVersionEqualsTo);

        return $newContent;
    }

    private function replaceThroughDependencies(array $dependencies, string $dependency,
                                                string $dependencyNewVersion, ?string $onlyIfOldVersionEqualsTo = null): array
    {
        foreach ($dependencies as $dependencyName => &$dependencyVersion) {
            if ($dependencyName === $dependency) {
                if ($onlyIfOldVersionEqualsTo === null || $onlyIfOldVersionEqualsTo === $dependencyVersion) {
                    $dependencyVersion = $dependencyNewVersion;
                    break;
                }
            }
        }
        return $dependencies;
    }
}