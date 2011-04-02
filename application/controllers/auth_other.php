<?php

class auth_other extends CI_Controller 
{	
	function __construct()
	{
		parent::__construct();
		$this->load->model('facebook_model');
		$this->load->model('user_model');
		$this->load->model('tank_auth/users');
		$this->load->library('tank_auth');
	}
	
	// handle when users log in using facebook account
	function fb_signin()
	{
		// get the facebook user and save in the session
		$fb_user = $this->facebook_model->getUser();
		if( isset($fb_user))
		{
			$this->session->set_userdata('facebook_id', $fb_user['id']);
			$user = $this->user_model->get_user_by_facebook_id($fb_user['id']);
			if( sizeof($user) == 0) { redirect('auth_other/fill_user_info', 'refresh'); }
			else
			{
				// simulate what happens in the tank auth
				$this->session->set_userdata(array(	'user_id' => $user[0]->id, 'username' => $user[0]->username,
													'status' => ($user[0]->activated == 1) ? STATUS_ACTIVATED : STATUS_NOT_ACTIVATED));
				//$this->tank_auth->clear_login_attempts($user[0]->email); can't run this when doing FB
				$this->users->update_login_info( $user[0]->id, $this->config->item('login_record_ip', 'tank_auth'), 
												 $this->config->item('login_record_time', 'tank_auth'));
				redirect('auth', 'refresh');
			}
		}
		else { echo 'cannot find the Facebook user'; }
	}
	
	// function to allow users to log in via twitter
	function twitter_signin()
	{
		// It really is best to auto-load this library!
		//$this->load->library('tweet'); // automatically loaded in the autoload!
		
		// Enabling debug will show you any errors in the calls you're making, e.g:
		$this->tweet->enable_debug(TRUE);
		
		// If you already have a token saved for your user
		// (In a db for example) - See line #37
		// 
		// You can set these tokens before calling logged_in to try using the existing tokens.
		// $tokens = array('oauth_token' => 'foo', 'oauth_token_secret' => 'bar');
		// $this->tweet->set_tokens($tokens);
		if ( !$this->tweet->logged_in() )
		{
			// This is where the url will go to after auth.
			// ( Callback url )
			
			$this->tweet->set_callback(site_url('auth_other/twitter_signin'));
			
			// Send the user off for login!
			$this->tweet->login();
		}
		else
		{
			// You can get the tokens for the active logged in user:
			// $tokens = $this->tweet->get_tokens();
			
			// 
			// These can be saved in a db alongside a user record
			// if you already have your own auth system.
			
			// get the user id from twitter authentication and save to session
			$user = $this->tweet->call('get', 'account/verify_credentials');
			$twitter_id = $user->id;
			$this->session->set_userdata('twitter_id', $twitter_id);	
			
			// now see if the user exists with the given twitter id	
			$user = $this->user_model->get_user_by_twitter_id($twitter_id);
			if( sizeof($user) == 0 ) { redirect('auth_other/fill_user_info', 'refresh'); }
			else
			{
				// simulate what happens in the tank auth
				$this->session->set_userdata(array(	'user_id' => $user[0]->id, 'username' => $user[0]->username,
													'status' => ($user[0]->activated == 1) ? STATUS_ACTIVATED : STATUS_NOT_ACTIVATED));
				//$this->tank_auth->clear_login_attempts($user[0]->email); can't run this when doing twitter
				redirect('main', 'refresh');	
			}			
		}		
	}
	
	// function to allow users to log in via twitter
	/*function twitter_signin()
	{
		//save the authentication tokens in the session
		$tokens['access_token'] = NULL;
		$tokens['access_token_secret'] = NULL;

		// GET THE ACCESS TOKENS
		$oauth_tokens = $this->session->userdata('twitter_oauth_tokens');
		if ( $oauth_tokens !== FALSE ) { $tokens = $oauth_tokens; }
		$this->load->library('twitter');
		$auth = $this->twitter->oauth( $this->config->item('twitter_consumer_key'), $this->config->item('twitter_consumer_key_secret'), 
									   $tokens['access_token'], $tokens['access_token_secret']);

		if ( isset($auth['access_token']) && isset($auth['access_token_secret']) )
		{
			// SAVE THE ACCESS TOKENS		
			$this->session->set_userdata('twitter_oauth_tokens', $auth);
			if ( isset($_GET['oauth_token']) )
			{
				$uri = $_SERVER['REQUEST_URI'];
				$parts = explode('?', $uri);

				// Now we redirect the user since we've saved their stuff!
				header('Location: '.$parts[0]);
				return;
			}
		}
		// get the user id from twitter authentication and save to session
		$data = $this->twitter->call('account/verify_credentials');
		$twitter_id = $data->id;
		$this->session->set_userdata('twitter_id', $twitter_id);	
		
		// now see if the user exists with the given twitter id	
		$user = $this->user_model->get_user_by_twitter_id($twitter_id);
		if( sizeof($user) == 0 ) { redirect('auth_other/fill_user_info', 'refresh'); }
		else
		{
			// simulate what happens in the tank auth
			$this->session->set_userdata(array(	'user_id' => $user[0]->id, 'username' => $user[0]->username,
												'status' => ($user[0]->activated == 1) ? STATUS_ACTIVATED : STATUS_NOT_ACTIVATED));
			//$this->tank_auth->clear_login_attempts($user[0]->email); can't run this when doing twitter
			redirect('main', 'refresh');	
		}
	}*/
	
	// called when user logs in via facebook/twitter for the first time
	function fill_user_info()
	{
		// load validation library and rules
		$this->load->config('tank_auth', TRUE);
		$this->load->library('form_validation');
		$this->form_validation->set_rules('username', 'Username', 'trim|required|xss_clean|min_length['.$this->config->item('username_min_length', 'tank_auth').']');
		$this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email|callback_email_check');
		
		// Run the validation
		if ($this->form_validation->run() == false ) 
		{
			$this->load->view('auth_other/fill_user_info'); 
		}
		else
		{
			$username = mysql_real_escape_string($this->input->post('username'));
			$email = mysql_real_escape_string($this->input->post('email'));
			
			/*
			 * We now must create a new user in tank auth with a random password in order
			 * to insert this user and also into user profile table with tank auth id
			 */
			$password = $this->generate_password(9, 8);
			$this->tank_auth->create_user($username, $email, $password, false);
			$new_user = $this->user_model->get_user_by_email($email);
			$user_id = $new_user[0]->id;
			if( $this->session->userdata('facebook_id')) 
			{ 
				$facebook_id = $this->session->userdata('facebook_id');
				$this->user_model->update_facebook_user_profile($user_id, $facebook_id);
			}
			else if( $this->session->userdata('twitter_id'))
			{
				$twitter_id = $this->session->userdata('twitter_id');
				$this->user_model->update_twitter_user_profile($user_id, $twitter_id);
			}
									
			// let the user login via tank auth
			$this->tank_auth->login($email, $password, false, false, true);
			redirect('auth', 'refresh');
		}
	}
		
	// function to validate the email input field
	function email_check($email)
	{
		$user = $this->user_model->get_user_by_email($email);
		if ( sizeof($user) > 0) 
		{
			$this->form_validation->set_message('email_check', 'This %s is already registered.');
			return false;
		}
		else { return true; }
	}
	
	// generates a random password for the user
	function generate_password($length=9, $strength=0) 
	{
		$vowels = 'aeuy';
		$consonants = 'bdghjmnpqrstvz';
		if ($strength & 1) { $consonants .= 'BDGHJLMNPQRSTVWXZ'; }
		if ($strength & 2) { $vowels .= "AEUY"; }
		if ($strength & 4) { $consonants .= '23456789'; }
		if ($strength & 8) { $consonants .= '@#$%'; }
	 
		$password = '';
		$alt = time() % 2;
		for ($i = 0; $i < $length; $i++) 
		{
			if ($alt == 1) 
			{
				$password .= $consonants[(rand() % strlen($consonants))];
				$alt = 0;
			} 
			else 
			{
				$password .= $vowels[(rand() % strlen($vowels))];
				$alt = 1;
			}
		}
		return $password;
	}
}

/* End of file main.php */
/* Location: ./freally_app/controllers/main.php */