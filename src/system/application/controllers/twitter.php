<?php
/**
 * Twitter pages controller.
 *
 * PHP version 5
 *
 * @category  Joind.in
 * @package   Controllers
 * @copyright 2009 - 2010 Joind.in
 * @license   http://github.com/joindin/joind.in/blob/master/doc/LICENSE JoindIn
 * @link      http://github.com/joindin/joind.in
 */

/** Required for inheritance */
require 'AuthAbstract.php';

/**
 * Twitter pages controller.
 *
 * Responsible for handling twitter actions, such as oAuth.
 *
 * In order for the oAuth authentication to work you need to have a key and a
 * secret; these can be obtained by registering the Joind.in application with
 * twitter at: https://dev.twitter.com/apps.
 *
 * Do not forget to enter the joind.in access_token url in the 'Callback URL'
 * field in the twitter application's settings page.
 *
 * For test this should be: http://test.joind.in/twitter/access_token
 * For prod this should be: https://joind.in/twitter/access_token
 *
 * Afterwards you can set the twitter_consumer_key and twitter_consumer_secret
 * keys in the configuration.
 *
 * @category  Joind.in
 * @package   Controllers
 * @copyright 2009 - 2010 Joind.in
 * @license   http://github.com/joindin/joind.in/blob/master/doc/LICENSE JoindIn
 * @link      http://github.com/joindin/joind.in
 *
 * @property  CI_Config     $config
 * @property  CI_Session    $session
 * @property  CI_Loader     $load
 * @property  CI_Template   $template
 * @property  User_model    $user_model
 * @property  twitter_oauth $twitter_oauth
 */
class Twitter extends AuthAbstract
{

    /**
     * Log in via Twitter
     *
     * @return null
     */
    public function request_token()
    {
        $this->loadTwitterLibrary();
        if ($this->session->flashdata('url_after_login')) {
            // Keep the URL to go to after a login (used in joind.in OAuth authentication)
            $this->session->set_flashdata('url_after_login', $this->session->flashdata('url_after_login'));
        }
        $response = $this->twitter_oauth->get_request_token(
            site_url("twitter/access_token")
        );

        if ($response['token_secret'] === null) {
            show_error(
                'Twitter has returned an error, have you created an application '
                . 'with twitter and entered the correct callback URL, and the '
                . 'resulting key and secret in the configuration?'
            );
        }
        $this->session->set_userdata(
            'twitter_token_secret',
            $response['token_secret']
        );
        redirect($response['redirect']);
    }

    /**
     * Allows the user to provide authorization to connect to their 
     * twitter account.
     *
     * @return null
     */
    public function access_token()
    {
        $this->load->model('user_model');
        $this->loadTwitterLibrary();

        $response = $this->twitter_oauth->get_access_token(
            false, $this->session->userdata('twitter_token_secret')
        );

        if (!isset($response['screen_name'])) {
            show_error(
                'An error occurred during communication with Twitter, please'
                .' try again later'
            );
        }

        $user = current(
            $this->user_model->getUserByTwitter($response['screen_name'])
        );

        if ($user) {
            $this->_login($user);
        } else {
            $user_info = $this->getTwitterUserdata($response['screen_name']);
            $ret       = $this->_addUser(
                $this->user_model->findAvailableUsername($response['screen_name']),
                '', '', $user_info->name, $response['screen_name']
            );

            $this->session->set_userdata((array)$ret);

            // Do we have a URL to go to afterwards?
            $redirectAfterLogin = $this->session->flashdata('url_after_login');
            if ($redirectAfterLogin) {
                redirect($redirectAfterLogin); // go to it
            }

            // No predefined URL, so since they're now set up,
            // log them in and push them to the account
            // management page
            $this->session->set_flashdata(
                'msg',
                'To receive notifications; please enter your e-mail address.'
                .'<br />Without a password you can only log in using your '
                .'twitter account.'
            );
            redirect('user/manage');
        }
    }

    /**
     * Loads the twitter library
     *
     * @return null
     */
    protected function loadTwitterLibrary()
    {
        $this->load->library(
            'twitter_oauth', array(
                'key'    => $this->config->item('twitter_consumer_key'),
                'secret' => $this->config->item('twitter_consumer_secret')
            )
        );
    }

    /**
     * Retrieves a user's twitter information
     *
     * @param string $screen_name Twitter screen name
     *
     * @return stdClass
     */
    protected function getTwitterUserdata($screen_name)
    {
        $this->load->library('curl');

        return json_decode(
            $this->curl->simple_get(
                'https://api.twitter.com/1/users/show.json?screen_name='
                . $screen_name
            )
        );
    }
}
