<?php use_helper('sfFacebookConnect'); ?>
<?php 
  slot('fb_connect');
  include_facebook_connect_script();
  end_slot();
?>

<h1>
  Welcome <fb:name uid="<?php echo $sf_user->getCurrentFacebookUid() ?>" useyou="false" ></fb:name> !
  <fb:profile-pic uid="<?php echo $sf_user->getCurrentFacebookUid() ?>" linked="true" ></fb:profile-pic>
</h1>

<pre>
<?php 

//$ret = sfFacebook::getFacebookApi("me/friends");

$ret = sfFacebook::getFacebookClient()->getAppId();
var_dump("APP_ID:".$ret);


$ret = sfFacebook::getFacebookCookie();
var_dump($ret);

$ret = sfFacebook::getFacebookApi("me");
var_dump($ret);

$ret = sfFacebook::getFacebookApi("me/picture");
var_dump($ret);

//$ret = sfFacebook::getFacebookClient()->api("me/feed", "POST", array(
//    'message' => 'Check out this funny article',
//    'link' => 'http://www.example.com/article.html',
//    'picture' => 'http://www.example.com/article-thumbnail.jpg',
//    'name' => 'Article Title',
//    'caption' => 'Caption for the link',
//    'description' => 'Longer description of the link',
//    'actions' => '{"name": "Profil ansehen", "link": "http://www.zombo.com"}' 
//));
//var_dump($ret);
//
$url = sfFacebook::getFacebookClient()->getLoginUrl();
var_dump($url);


?>
</pre>
<br />
<br />

<div>
  <?php if ($sf_user->isAuthenticated()): ?>
    Verbunden als: <?php echo $sf_user->getGuardUser()->getUsername() ?>
  <?php else: ?>
    Connect via Facebook!
    <?php echo facebook_connect_button(); ?>
  <?php endif; ?>
</div>




<!-- just before body in layout to avoid problems in IE -->
<?php if (has_slot('fb_connect')): ?>
  <?php //include_slot('fb_connect') ?>
<?php endif; ?>
