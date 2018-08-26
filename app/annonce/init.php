<?php

use App\Ad\Ad;

$storageType = $config->get("storage", "type", "files");
if ($storageType == "db") {
    $storage = new \App\Storage\Db\Ad($dbConnection, $userAuthed);
} else {
    $storage = new \App\Storage\File\Ad(DOCUMENT_ROOT."/var/configs/backup-ads-".$auth->getUsername().".csv");
}

$adPhoto = new App\Storage\AdPhoto($userAuthed);

$check_ad_online = function (Ad $ad) use ($app, $storage) {
    $now = new DateTime();

    if ($ad->getOnlineDateChecked()) {
        $date_ad = new DateTime($ad->getOnlineDateChecked());
        $date_ad->modify("+1 day");
        if ($now < $date_ad) {
            return false;
        }
    }

    if (!isset($date_ad)) {
        $date_ad = new DateTime();
    }

    // Vérifie si l'annonce est en ligne
    $connector = $app->getConnector($ad->getLink())
                     ->setFollowLocation(true);
    $content = $connector->request();
    $ad->setOnlineDateChecked($now->format("Y-m-d H:i:s"));
    $ad->setOnline(true);

    if ($ad->getLink() != $connector->getUrl()) {
        $ad->setLink($connector->getUrl());
    }

    $code = $connector->getRespondCode();

    if (in_array($code, array(404, 410))) {
        $ad->setOnline(false);

    } elseif (false !== strpos($content, "Cette annonce est désactivée")) {
        $ad->setOnline(false);

    } elseif (200 != $connector->getRespondCode()) {
        $date_ad->modify("-1 day +1 hour");
        $ad->setOnlineDateChecked($date_ad->format("Y-m-d H:i:s"));
    }

    $storage->save($ad);

    return true;
};
