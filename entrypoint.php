<?php

require 'vendor/autoload.php';

$client = new \Github\Client();

$username = 'organizationname';
$path = 'README.md';
$repo = 'testrepo';
$token = '****';
$newBranchName = 'new-branch1';
$pullRequestBody = 'Pull request body';
$pullRequestTitle = 'Pull request title';
$possibleBaseBranches = array('develop', 'dev', 'master');
$dependency = 'knplabs/github-api';
$onlyIfOldVersionEqualsTo = '^2.8';
$newVersion = '^100500.8';

$client->authenticate($token, null, \Github\Client::AUTH_HTTP_TOKEN);

/** @var \Github\Api\Repo $repoApi */
$repoApi = $client->api('repo');

$oldFileContentBase64 = $repoApi->contents()->show($username, $repo, $path);
$oldFileContent = base64_decode($oldFileContentBase64['content']);

$oldFileContent = json_decode($oldFileContent, true);
// @todo handle NULL

$composerJsonReplacer = new \VersionPullRequester\ComposerJsonReplacer();
$newFileContent = $composerJsonReplacer->replaceVersionOfDependency($oldFileContent, $dependency, $newVersion, $onlyIfOldVersionEqualsTo);

/** @var \Github\Api\GitData $gitDataApi */
$gitDataApi = $client->api('gitData');

$branches = $gitDataApi->references()->branches($username, $repo);

function chooseBaseBranch(array $branches, $possibleBaseBranches, &$chosenBranch) {
    $branchPrefixInRef = 'refs/heads/';

    foreach ($possibleBaseBranches as $possibleBaseBranch) {
        foreach ($branches as $branch) {
            if ($branch['ref'] === $branchPrefixInRef . $possibleBaseBranch) {
                $chosenBranch = $possibleBaseBranch;
                return $branch['object']['sha'];
            }
        }
    }

    return null;
}

$branchShaToForkFrom = chooseBaseBranch($branches, $possibleBaseBranches,$chosenBranch);

$referenceData = ['ref' => 'refs/heads/' . $newBranchName, 'sha' => $branchShaToForkFrom];
$reference = $gitDataApi->references()->create($username, $repo, $referenceData);

$result = $repoApi->contents()->update(
    $username,
    $repo,
    $path,
    $newFileContent,
    'commit',
    $oldFileContentBase64['sha'],
    $newBranchName
);

/** @var \Github\Api\PullRequest $pullRequestApi */
$pullRequestApi = $client->api('pull_request');

$pullRequest = $pullRequestApi->create($username, $repo, array(
    'base'  => $chosenBranch,
    'head'  => $newBranchName,
    'title' => $pullRequestTitle,
    'body'  => $pullRequestBody,
));
