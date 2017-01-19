<?php

require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/local.php';

$deleteFlag = in_array('--delete', $argv);
$clientId = $config['client_id'];
$auth = $config['auth'];

$clientAPI = new CS_REST_Clients($clientId, $auth);
$listsResult = $clientAPI->get_lists();

$now = new \DateTime();

/**
 * Find out which lists have been used in campaigns recently
 */

$draftCampaignResult = $clientAPI->get_drafts();
$scheduledCampaignResult = $clientAPI->get_scheduled();
$sentCampaignResult = $clientAPI->get_campaigns();

// filter out drafts made more than 6 months ago
$draftCampaignResult->response = array_filter($draftCampaignResult->response, function($draft) use ($now) {
    $sent = \DateTime::createFromFormat('Y-m-d H:i:s', $draft->DateCreated);
    $dateDiff = $now->diff($sent);

    return ($dateDiff->y === 0 && $dateDiff->m < 6);
});

// filter out campaigns sent more than 3 months ago
$sentCampaignResult->response = array_filter($sentCampaignResult->response, function($campaign) use ($now) {
    $sent = \DateTime::createFromFormat('Y-m-d H:i:s', $campaign->SentDate);
    $dateDiff = $now->diff($sent);

    return ($dateDiff->y === 0 && $dateDiff->m < 3);
});

$draftListIds = extractListIdsForCampaigns($draftCampaignResult, $auth);
$scheduledListIds = extractListIdsForCampaigns($scheduledCampaignResult, $auth);
$sentListIds = extractListIdsForCampaigns($sentCampaignResult, $auth);

$excludedListIds = array_merge($draftListIds, $scheduledListIds, $sentListIds);
$excludedListIds = array_unique($excludedListIds);

echo PHP_EOL;

/**
 * Iterate lists, workout which lists can be deleted
 */

$total = 0;
$deleting = 0;
$toDelete = array();

foreach($listsResult->response as $listmeta){
    $total++;
    echo 'List ID: ' . $listmeta->ListID . PHP_EOL;
    echo 'List Name: ' . $listmeta->Name . PHP_EOL;

    if(in_array($listmeta->ListID, $excludedListIds)){
        echo 'List is used in a draft, scheduled or recently sent campaign, skipping' . PHP_EOL;
        echo PHP_EOL;
        continue;
    }

    $listAPI = new CS_REST_Lists($listmeta->ListID, $auth);
    $listSubscribersResult = $listAPI->get_active_subscribers('', null, 10, 'DATE', 'DESC');

    if($listSubscribersResult->response->TotalNumberOfRecords < 1){
        echo 'List is empty, skipping' . PHP_EOL;
        echo PHP_EOL;
        continue;
    }

    $subscribers = $listSubscribersResult->response->Results;
    $latestSubscriber = array_shift($subscribers);

    $lastModified = \DateTime::createFromFormat('Y-m-d H:i:s', $latestSubscriber->Date);
    $dateDiff = $now->diff($lastModified);

    echo 'Last subscriber added: ' . $lastModified->format('Y-m-d') . PHP_EOL;

    if($dateDiff->y === 0 &&  $dateDiff->m < 3){
        echo 'List has a subscriber within the last three months, skipping' . PHP_EOL;
        echo PHP_EOL;
        continue;
    }

    echo "List scheduled for deletion" . PHP_EOL;

    $toDelete[] = $listmeta->ListID;
    $deleting++;
    echo PHP_EOL;
}

echo "Deleting $deleting/$total lists" . PHP_EOL;

/**
 * Do the deletion if flag set
 */
if($deleteFlag){
    foreach($toDelete as $listId){
        echo "Deleting list $listId" . PHP_EOL;

        $listAPI = new CS_REST_Lists($listId, $auth);
        $listAPI->delete();
    }
}else{
    echo "Use --delete argument to actually delete these lists" . PHP_EOL;
}


/**
 * Function definitions
 */
function extractListIdsForCampaigns(CS_REST_Wrapper_Result $result, $auth){
    $listIds = array();

    foreach($result->response as $campaignmeta){
        $campaignAPI = new CS_REST_Campaigns($campaignmeta->CampaignID, $auth);
        $campaignResult = $campaignAPI->get_lists_and_segments();

        foreach($campaignResult->response->Lists as $listmeta){
            $listIds[] = $listmeta->ListID;
        }
    }

    return array_unique($listIds);
}
