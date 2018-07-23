<?php

require 'vendor/autoload.php';

$client = new \Github\Client();

$username = 'alexeyvy';
$organization = 'organization';
$path = 'composer.json';
$token = '****';
$newBranchName = 'fix-version-of-extension-installer';
$pullRequestBody = 'In order to raise the minimum-stability of a tao install, we need to be able to install the extension installer in a released version. To this end all extensions need to move from "oat-sa/oatbox-extension-installer:dev-master" to "oat-sa/oatbox-extension-installer:~1.1||dev-master". Details: https://oat-sa.atlassian.net/browse/TAO-6542';
$pullRequestTitle = 'Fix version of extension installer';
$possibleBaseBranches = array('develop', 'dev', 'devel', 'master');
$dependency = 'oat-sa/oatbox-extension-installer';
$onlyIfOldVersionEqualsTo = 'dev-master';
$newVersion = '~1.1||dev-master';

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
$i = 0;
foreach ($repos as $repoParams) {
    $repo = $repoParams['name'];
    printf('Scanning repo %s (%d)' . PHP_EOL, $repo, ++$i);
    // pick a branch that will be used as a base one

    /** @var \Github\Api\GitData $gitDataApi */
    $gitDataApi = $client->api('gitData');
    try {
        $branches = $gitDataApi->references()->branches($organization, $repo);
    } catch (\Github\Exception\RuntimeException $e) {
        fwrite(STDERR, sprintf('Repo %s is empty' . PHP_EOL, $repo));
        continue;
    }

    // check our branch not created yet

    foreach ($branches as $branch) {
        $branchPrefixInRef = 'refs/heads/';
        if ($branch['ref'] === $branchPrefixInRef . $newBranchName) {
            fwrite(STDERR, sprintf('Repo %s was already processed' . PHP_EOL, $repo));
            continue(2);
        }
    }

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

    if ($newFileContent === $oldFileContent) {
        printf('No replace for repo %s' . PHP_EOL, $repo);
        continue;
    }

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
            'Fix version of extension installer',
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

    printf('Pull request has successfully been created for repo %s' . PHP_EOL, $repo);
}
