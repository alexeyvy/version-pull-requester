<?php

namespace VersionPullRequester;

class JsonReplacer implements Replacer
{
    public function replaceVersionOfDependency(string $oldContent, string $dependency,
                                               string $dependencyNewVersion, ?string $onlyIfOldVersionEqualsTo = null): string
    {
        $oldContent = json_decode($oldContent, true);
        // @todo handle NULL

        $newContent = $oldContent;

        $newContent['require'] = $this->replaceThroughDependencies($oldContent['require'], $dependency, $dependencyNewVersion, $onlyIfOldVersionEqualsTo);
        $newContent['require-dev'] = $this->replaceThroughDependencies($oldContent['require-dev'], $dependency, $dependencyNewVersion, $onlyIfOldVersionEqualsTo);

        return json_encode($newContent, JSON_PRETTY_PRINT);
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