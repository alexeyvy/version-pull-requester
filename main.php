<?php

require 'vendor/autoload.php';

$client = new \Github\Client();

$username = 'sdfdsf324a';
$path = 'README.md';
$repo = 'test';

$client->authenticate('9881cd634457760589d7c5af3cf59c27d725b99f', null, \Github\Client::AUTH_HTTP_TOKEN);

$oldFileContentBase64 = $client->api('repo')->contents()->show($username, $repo, 'README.md');

$oldFileContent = base64_decode($oldFileContentBase64['content']);

$branches = $client->api('gitData')->references()->branches($username, $repo);

$branchToForkFrom = 'master';
$branchShaToForkFrom = $branches[0]['object']['sha'];


$referenceData = ['ref' => 'refs/heads/new-branch', 'sha' => $branchShaToForkFrom];
//$reference = $client->api('gitData')->references()->create($username, $repo, $referenceData);
$result = $client->api('repo')->contents()->update(
    $username,
    $repo,
    $path,
    'new content2',
    'commit',
    $oldFileContentBase64['sha'],
    'new-branch'
);