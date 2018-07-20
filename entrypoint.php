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

function chooseBaseBranch(array $branches, array $possibleBaseBranches, &$chosenBranch): ?string
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
    printf('Scanning repo %s', $repo);

    // pick a branch that will be used as a base one

    /** @var \Github\Api\GitData $gitDataApi */
    $gitDataApi = $client->api('gitData');
    $branches = $gitDataApi->references()->branches($organization, $repo);
    $branchShaToForkFrom = chooseBaseBranch($branches, $possibleBaseBranches, $chosenBranch);
    if ($branchShaToForkFrom === null) {
        fwrite(STDERR, sprintf('Repo %s does not have branch to be base' . PHP_EOL, $repo));
        continue;
    }

    // get file content on the base branch

    /** @var \Github\Api\Repo $repoApi */
    $refToForkFrom = 'refs/heads/' . $chosenBranch;
    $repoApi = $client->api('repo');
    try {
        $oldFileContentBase64 = $repoApi->contents()->show($organization, $repo, $path, $refToForkFrom);
    } catch (\Github\Exception\RuntimeException $e) {
        fwrite(STDERR, sprintf('Repo %s does not contains %s on %s: %s' . PHP_EOL, $repo, $path, $refToForkFrom, $e->getMessage()));
        continue;
    }
    $oldFileContent = base64_decode($oldFileContentBase64['content']);

    // compute new file

    $composerJsonReplacer = new \VersionPullRequester\RegexReplacer();
    $newFileContent = $composerJsonReplacer->replaceVersionOfDependency($oldFileContent, $dependency, $newVersion, $onlyIfOldVersionEqualsTo);

    // fork branch

    $referenceData = ['ref' => 'refs/heads/' . $newBranchName, 'sha' => $branchShaToForkFrom];
    try {
        $reference = $gitDataApi->references()->create($organization, $repo, $referenceData);
    } catch (\Github\Exception\RuntimeException $e) {
        fwrite(STDERR, sprintf('Repo %s can not create a branch: %s' . PHP_EOL, $repo, $e->getMessage()));
        continue;
    }

    // push new file

    try {
        $result = $repoApi->contents()->update(
            $organization,
            $repo,
            $path,
            $newFileContent,
            'commit',
            $oldFileContentBase64['sha'],
            $newBranchName
        );
    } catch (\Github\Exception\RuntimeException $e) {
        fwrite(STDERR, sprintf('Can not push new file to repo %s: %s' . PHP_EOL, $repo, $e->getMessage()));
        // @todo think about deleting a branch that has just been created in order to make all the process transactional
        continue;
    }

    // create pull request

    /** @var \Github\Api\PullRequest $pullRequestApi */
    $pullRequestApi = $client->api('pull_request');

    try {
        $pullRequest = $pullRequestApi->create($organization, $repo, array(
            'base' => $chosenBranch,
            'head' => $newBranchName,
            'title' => $pullRequestTitle,
            'body' => $pullRequestBody,
        ));
    } catch (\Github\Exception\RuntimeException $e) {
        fwrite(STDERR, sprintf('Can not create pull request for repo %s: %s' . PHP_EOL, $repo, $e->getMessage()));
        // @todo think about deleting a branch that has just been created in order to make all the process transactional
        continue;
    }
}
