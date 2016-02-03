<?php
session_start();
require_once 'Autoloader.php';

use ModuleSSO\Client;
use ModuleSSO\Client\LoginHelper\HTTP\NoScriptHelper;

Database::init();
$client = new Client();
$client->setLoginHelper(new NoScriptHelper());
$client->run();
?>

<html>
    <head>
        <title><?php echo CFG_DOMAIN_DISPLAY_NAME ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <link rel="icon" type="image/png" href="/img/favicon.png" />
        <link rel="stylesheet" href="http://sso.local/css/common.styles.css">
        <?php $client->appendStyles(); ?>
        <?php $client->appendScripts(); ?>
        <link rel="stylesheet" href="css/material.min.css">
        <link rel="stylesheet" href="css/styles.css">
        
        <script src="js/material.min.js"></script>
        <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
        <link rel="stylesheet" href="http://fonts.googleapis.com/css?family=Roboto:300,400,500,700" type="text/css">
    </head>
    <body>
        <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
            <header class="mdl-layout__header">
                <div class="mdl-layout__header-row">
                  <!-- Title -->
                    <span class="mdl-layout-title"><?php echo CFG_DOMAIN_DISPLAY_NAME ?></span>
                    <!-- Add spacer, to align navigation to the right -->
                    <div class="mdl-layout-spacer"></div>
                    <!-- Navigation. We hide it in small screens. -->
                    <nav class="mdl-navigation mdl-layout--large-screen-only">
                        <a class="mdl-navigation__link" href="/">Home</a>
                       <a class="mdl-navigation__link" href="/cross.php">Login crossroads</a>
                       <a class="mdl-navigation__link" href="/?logout=1">Local logout</a>
                       <a class="mdl-navigation__link" href="/?glogout=1">Global logout</a>
                    </nav>
                </div>
            </header>
            <div class="mdl-layout__drawer">
                <span class="mdl-layout-title"><?php echo CFG_DOMAIN_DISPLAY_NAME ?></span>
                <nav class="mdl-navigation">
                    <a class="mdl-navigation__link" href="/">Home</a>
                    <a class="mdl-navigation__link" href="/cross.php">Login crossroads</a>
                    <a class="mdl-navigation__link" href="/?logout=1">Local logout</a>
                    <a class="mdl-navigation__link" href="/?glogout=1">Global logout</a>
                </nav>
            </div>
            <main class="mdl-layout__content">
                <div class="page-content">
                    <div class="grid-centered">
                        <div id="id-messages">
                            <?php echo $client->showMessages(); ?>
                        </div>
                        <h1><?php echo CFG_DOMAIN_DISPLAY_NAME ?> homepage</h1>
                        <section id="id-client-info">
                            <?php if (isset($_SESSION['uid'])): ?>
                            <?php $user = $client->getUser() ?>
                            <div class="card-wrap">
                                <div class="mdl-card--border mdl-shadow--2dp">
                                     <div class="mdl-card__title mdl-card--expand">
                                        <h2 class="mdl-card__title-text">User info</h2>
                                    </div>
                                    <div class="mdl-card__supporting-text">
                                        <ul class="user-info">
                                            <li id="id-user-id">ID: <?php echo $user['id'] ?></li>
                                            <li>First name: <?php echo $user['first_name'] ?></li>
                                            <li>Last name: <?php echo $user['last_name'] ?></li>
                                            <li>Email: <?php echo $user['email'] ?></li>
                                        </ul>
                                    </div>
                                    <!--
                                    <div class="mdl-card__actions mdl-card--border">
                                        <a href="/?logout=1" class="button-full mdl-button mdl-js-button mdl-button--raised mdl-button--colored">
                                          Logout
                                        </a>
                                    </div>
                                    -->
                                </div>
                            </div>
                            <?php endif ?>
                        </section>
                        <section id="id-client-login">
                            <div class="card-wrap">
                                <?php 
                                if (!isset($_SESSION['uid'])) {
                                    $client->showLogin();
                                }
                                ?>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>