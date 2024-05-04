<?php
/*
 *  Made by Partydragen
 *  https://github.com/partydragen/Nameless-OAuth2
 *  https://partydragen.com/
 *
 *  OAuth2 page
 */

// Always define page name for navbar
define('PAGE', 'oauth2');
$page_title = $oauth2_language->get('general', 'oauth2');
require_once(ROOT_PATH . '/core/templates/frontend_init.php');

if (!isset($_GET['client_id'])) {
    require_once(ROOT_PATH . '/403.php');
    die();
}

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

$template->onPageLoad();

Session::put('application_client', $_GET['client_id']);

// Must be logged in
if(!$user->isLoggedIn()){
    Redirect::to(URL::build('/login'));
}

// Get application by client id
$application = new Application($_GET['client_id'], 'client_id');
if (!$application->exists()) {
    require_once(ROOT_PATH . '/403.php');
    die();
}

// Make sure redirect uri is set
if (empty($application->getRedirectURI())) {
    $errors[] = $oauth2_language->get('general', 'invalid_redirect_uri');
}

// Make sure redirect uri match
if (!isset($_GET['redirect_uri']) || $application->getRedirectURI() != $_GET['redirect_uri']) {
    $errors[] = $oauth2_language->get('general', 'invalid_redirect_uri');
}

// Skip user approval if enabled
if ($application->data()->skip_approval === 1) {
    // Generate a code
    $code = SecureRandom::alphanumeric();

    DB::getInstance()->insert('oauth2_tokens', [
        'application_id' => $application->data()->id,
        'user_id' => $user->data()->id,
        'code' => $code,
        'access_token' => SecureRandom::alphanumeric(),
        'refresh_token' => SecureRandom::alphanumeric(),
        'created' => date('U')
    ]);

    Redirect::to($application->getRedirectURI() . "&code=$code");
}

if (!isset($errors)) {
    if (Input::exists()) {
        if (Token::check(Input::get('token'))) {
            // Generate a code
            $code = SecureRandom::alphanumeric();

            DB::getInstance()->insert('oauth2_tokens', [
                'application_id' => $application->data()->id,
                'user_id' => $user->data()->id,
                'code' => $code,
                'access_token' => SecureRandom::alphanumeric(),
                'refresh_token' => SecureRandom::alphanumeric(),
                'created' => date('U')
            ]);

            Redirect::to($application->getRedirectURI() . "&code=$code");
        } else {
            // Invalid token
            $errors[] = $language->get('general', 'invalid_token');
        }
    }
    
    $access_to[] = $oauth2_language->get('general', 'your_username');
    $access_to[] = $oauth2_language->get('general', 'your_email');

    $smarty->assign([
        'APPLICATION_NAME' => Output::getClean($application->getName()),
        'APPLICATION_WANTS_ACCESS' => $oauth2_language->get('general', 'application_wants_access', [
            'application' => $application->getName(),
            'siteName' => SITE_NAME
        ]),
        'APPLICATION_WANTS_INFORMATION' => $oauth2_language->get('general', 'application_wants_information', [
            'application' => $application->getName()
        ]),
        'AUTHORIZE' => $oauth2_language->get('general', 'authorize'),
        'CANCEL' => $language->get('general', 'cancel'),
        'CANCEL_LINK' => URL::build('/'),
        'TOKEN' => Token::get(),
        'ACCESS_TO' => $access_to
    ]);
}

if (isset($success))
	$smarty->assign([
		'SUCCESS' => $success,
		'SUCCESS_TITLE' => $language->get('general', 'success')
	]);

if (isset($errors) && count($errors))
	$smarty->assign([
		'ERRORS' => $errors,
		'ERRORS_TITLE' => $language->get('general', 'error')
	]);

require(ROOT_PATH . '/core/templates/navbar.php');
require(ROOT_PATH . '/core/templates/footer.php');

// Display template
$template->displayTemplate('oauth2/oauth2.tpl', $smarty);