<html>
	<head>
		<!-- google friend connect -->
		<script type="text/javascript" src="http://www.google.com/jsapi"></script>
		<script type="text/javascript">google.load('friendconnect', '0.8');</script>
		<script type="text/javascript">
			google.friendconnect.container.setParentUrl('/' /* location of rpc_relay.html and canvas.html */);
			google.friendconnect.container.initOpenSocialApi({
			  site: '<?php echo $this->config->item('google_app_id'); ?>',
			  onload: function(securityToken) { initAllData(); }
			});
		
			// main initialization function for google friend connect
			function initAllData() 
			{
				var req = opensocial.newDataRequest();
			  	req.add(req.newFetchPersonRequest("OWNER"), "owner_data");
			  	req.add(req.newFetchPersonRequest("VIEWER"), "viewer_data");
			  	var idspec = new opensocial.IdSpec({
			      	'userId' : 'OWNER',
			      	'groupId' : 'FRIENDS'
			  	});
			  	req.add(req.newFetchPeopleRequest(idspec), 'site_friends');
			  	//req.send(onData);
			};		
		</script>	
	</head>
	
	<body>
		
		Hi, <strong><?php echo $username; ?></strong>! You are logged in now. 
		
		<?php
			if( $this->session->userdata('facebook_id'))
			{
		?>
				<a href="http://m.facebook.com/logout.php?confirm=1&next=<?php echo site_url('auth/logout'); ?>">logout</a>
		<?php		
			}
			else if( $this->session->userdata('gfc_id'))
			{
		?>
				<a href='<?php echo site_url('auth/logout'); ?>' onclick='google.friendconnect.requestSignOut()'>Sign out</a>
		<?php		
			}
			else
			{
				echo anchor('/auth/logout/', 'Logout');
			}
		?>
	</body>
</html>