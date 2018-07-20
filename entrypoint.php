<?php

require 'vendor/autoload.php';

$client = new \Github\Client();

$username = 'username';
$organization = 'organization';
$path = 'composer.json';
$repo = 'testrepo';
$token = '****';
$newBranchName = 'new-branch100501';
$pullRequestBody = 'Pull request body';
$pullRequestTitle = 'Pull request title';
$possibleBaseBranches = array('develop', 'dev', 'master');
$dependency = 'knplabs/github-api';
$onlyIfOldVersionEqualsTo = '^2.8';
$newVersion = '^100500.8';

function chooseBaseBranch(array $branches, array $possibleBaseBranches, &$chosenBranch)
{
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

$client->authenticate($token, null, \Github\Client::AUTH_HTTP_TOKEN);

/** @var \Github\Api\User $userApi */
$userApi = $client->api('user');
$repos = $userApi->repositories($organization);

foreach ($repos as $repoParams) {
    $repo = $repoParams['name'];

    /** @var \Github\Api\Repo $repoApi */
    $repoApi = $client->api('repo');

    $oldFileContentBase64 = $repoApi->contents()->show($organization, $repo, $path);
    $oldFileContent = base64_decode($oldFileContentBase64['content']);

    $oldFileContent = json_decode($oldFileContent, true);
    // @todo handle NULL

    $composerJsonReplacer = new \VersionPullRequester\ComposerJsonReplacer();
    $newFileContent = $composerJsonReplacer->replaceVersionOfDependency($oldFileContent, $dependency, $newVersion, $onlyIfOldVersionEqualsTo);
    $newFileContent = json_encode($newFileContent);

    /** @var \Github\Api\GitData $gitDataApi */
    $gitDataApi = $client->api('gitData');
    $branches = $gitDataApi->references()->branches($organization, $repo);
    $branchShaToForkFrom = chooseBaseBranch($branches, $possibleBaseBranches, $chosenBranch);
    $referenceData = ['ref' => 'refs/heads/' . $newBranchName, 'sha' => $branchShaToForkFrom];
    $reference = $gitDataApi->references()->create($organization, $repo, $referenceData);
    $result = $repoApi->contents()->update(
        $organization,
        $repo,
        $path,
        $newFileContent,
        'commit',
        $oldFileContentBase64['sha'],
        $newBranchName
    );

    /** @var \Github\Api\PullRequest $pullRequestApi */
    $pullRequestApi = $client->api('pull_request');
    $pullRequest = $pullRequestApi->create($organization, $repo, array(
        'base' => $chosenBranch,
        'head' => $newBranchName,
        'title' => $pullRequestTitle,
        'body' => $pullRequestBody,
    ));
}
