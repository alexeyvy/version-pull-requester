<?php

namespace VersionPullRequester;

class RegexReplacer implements Replacer
{
    public function replaceVersionOfDependency(string $oldContent, string $dependency,
                                               string $dependencyNewVersion, ?string $onlyIfOldVersionEqualsTo = null): string
    {
        $pattern = sprintf('#"%s":(\s|\t)"(?<version>[^"]+)"#', preg_quote($dependency));
        preg_match($pattern, $oldContent, $matches, PREG_OFFSET_CAPTURE);

        if (!array_key_exists('version', $matches)) {
            return $oldContent;
        }

        $oldVersion = $matches['version'][0];
        $versionPosition = $matches['version'][1];

        if ($onlyIfOldVersionEqualsTo !== null && $onlyIfOldVersionEqualsTo !== $oldVersion) {
            return $oldContent;
        }

        $left = substr($oldContent, 0, $versionPosition);
        $right = substr($oldContent, $versionPosition + strlen($oldVersion));

        return $left . $dependencyNewVersion . $right;
    }
}