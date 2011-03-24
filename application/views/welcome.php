Hi, <strong><?php echo $username; ?></strong>! You are logged in now. 

<?php
	if( $this->session->userdata('facebook_id'))
	{
?>
		<a href="http://m.facebook.com/logout.php?confirm=1&next=<?php echo site_url('auth/logout'); ?>">logout</a>
<?php		
	}
	else
	{
		echo anchor('/auth/logout/', 'Logout');
	}
?>