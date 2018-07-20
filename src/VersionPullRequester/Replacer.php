<?php

namespace VersionPullRequester;

interface Replacer
{
    public function replaceVersionOfDependency(string $oldContent, string $dependency,
                                               string $dependencyNewVersion, ?string $onlyIfOldVersionEqualsTo = null): string;
}