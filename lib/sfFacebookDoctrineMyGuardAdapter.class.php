<?php

/**
 *
 * @package    sfFacebookConnectPlugin
 * @author     Fabrice Bernhard
 *
 */
class sfFacebookDoctrineGuardMyAdapter extends sfFacebookDoctrineGuardAdapter
{
   /**
   * gets the profile email
   *
   * @param sfGuardUser $user
   * @return string
   * @author fabriceb
   * @since 2009-05-17
   */
  public function getUserEmail(sfGuardUser $sfGuardUser)
  {
    return $sfGuardUser->getEmailAddress();
  }
  
  /**
   * gets a sfGuardUser using the facebook_uid column of his Profile class or his email_hash
   *
   * @param Integer $facebook_uid
   * @param boolean $isActive
   * @return sfGuardUser
   * @author fabriceb
   * @since 2009-05-17
   */
  public function getSfGuardUserByFacebookUid($facebook_data, $isActive = true)
  {
    $sfGuardUser = self::retrieveSfGuardUserByFacebookUid($facebook_data['id'], $isActive);
    
    if (!$sfGuardUser instanceof sfGuardUser && isset($facebook_data['email']))
    {
      if (sfConfig::get('sf_logging_enabled'))
      {
        sfContext::getInstance()->getLogger()->info('{sfFacebookConnect} No user exists with current facebook_uid');
      }
      $sfGuardUser = self::retrieveSfGuardUserByFacebookEmail($facebook_data['email'], $isActive);
    }
    
    return $sfGuardUser;
  }
  
  /**
   * Creates an empty sfGuardUser with profile field Facebook UID set
   *
   * @param Integer $facebook_uid
   * @return sfGuardUser
   * @author fabriceb
   * @since 2009-08-11
   */
  public function createSfGuardUserWithFacebookUid($facebook_data)
  {
    $con = Doctrine::getConnectionByTableName('sfGuardUser');

    return self::createSfGuardUserWithFacebookUidAndCon($facebook_data, $con);
  }
  
   /**
   * Creates an empty sfGuardUser with profile field Facebook UID set
   *
   * @param Integer $facebook_uid
   * @return sfGuardUser
   * @author fabriceb
   * @since 2009-05-17
   * @since 2009-08-11 ORM-agnostic version
   */
  public function createSfGuardUserWithFacebookUidAndCon($facebook_data, $con)
  {
    if(!isset($facebook_data['email'])) {
      throw new sfException('Please give us the permission to get your emailadress');
    }

    $sfGuardUser = new sfGuardUser();
    $FileManager = new sfFileManager();
    $this->setUserFacebookUid($sfGuardUser, $facebook_data['id']);
    sfFacebookConnect::newSfGuardConnectionHook($sfGuardUser, $facebook_data['id']);
    
    $check = new UserCheck();
    $check->setNewsletterChecked(sfConfig::get('app_sf_guard_plugin_check_newsletter', false));
    $check->setTermsChecked(sfConfig::get('app_sf_guard_plugin_check_terms', true));
    $check->setIsActivated(sfConfig::get('app_sf_guard_plugin_check_activated', true));

    if(sfConfig::get('app_sf_guard_plugin_check_terms', true)) {
      $check->setTermsDate(date("Y-m-d H:i:s"));
    }

    $sfGuardUser->setCheck($check);

    try
    {
      $ret = $facebook_data;
      //var_dump($ret);
      
      $sfGuardUser->setUsername($ret['name']);
      
      if ($ret['gender'] == "male")
        $sfGuardUser->setGender("m");
      else
        $sfGuardUser->setGender("w");
      
      $sfGuardUser->setFirstName($ret['first_name']);
      $sfGuardUser->setLastName($ret['last_name']);
          
      $sfGuardUser->setEmailAddress($ret['email']);
      $sfGuardUser->setDateOfBirth(date("Y-m-d", strToTime($ret['birthday'])));
      
      if ($url = $this->getFacebookPictureUrl())
         $content = file_get_contents($url);
      else
         $content = file_get_contents('https://graph.facebook.com/'.$facebook_data['id'].'/picture?type=large');
      
      if ($content)
      {
        $filename = $FileManager->save($content, $sfGuardUser->getUsername(), "image/jpeg");
        $sfGuardUser->setImage1($filename);
      }
      
      if ($ret['location'])
      {
        $city = substr($ret['location']['name'], 0, strpos($ret['location']['name'],","));
        $sfGuardUser->getProfile()->setCity($city);
      }
      
      if ($ret['bio'])
      {
        $sfGuardUser->getProfile()->setAboutMe($ret['bio']);
      }
      
    }
    catch (Exception $e)
    {
      return null;
    }


    // Save them into the database using a transaction to ensure a Facebook sfGuardUser cannot be stored without its facebook uid
    try
    {
      if (method_exists($con,'begin'))
      {
        $con->begin();
      }
      else
      {
        $con->beginTransaction();
      }
      $sfGuardUser->save();
      $sfGuardUser->getProfile()->save();
      $sfGuardUser->getCheck()->save();
      
      $sfGuardUser->addProfileImage();
      
      
      
      $con->commit();
    }
    catch (Exception $e)
    {
      $con->rollback();
      throw $e;
    }
    
    $sfGuardUser->addGroupByName("User");
    $sfGuardUser->save();
    
    $event = new UserEvent();
    $event->create($sfGuardUser, UserEvent::new_facebook, false, $sfGuardUser->getId());

    if(class_exists('CommunityToolkit') && method_exists('CommunityToolkit', 'setDefaultFriends')) {
      CommunityToolkit::setDefaultFriends($sfGuardUser);
    }

    return $sfGuardUser;
  }
  
  /**
   * gets a sfGuardUser using the facebook_uid column of his Profile class
   *
   * @param Integer $facebook_uid
   * @param boolean $isActive
   * @return sfGuardUser
   * @author fabriceb
   * @since 2009-05-17
   */
  public function retrieveSfGuardUserByFacebookEmail($facebook_email, $isActive = true)
  {
    $q = Doctrine_Query::create()
      ->from('sfGuardUser u')
      ->innerJoin('u.Profile p')
      ->andWhere('u.email_address = ?', $facebook_email)
      ->andWhere('u.is_active = ?', $isActive);

    if ($q->count())
    {

      return $q->fetchOne();
    }

    return null;
  }
  
  public function getFacebookPictureUrl()
  {
    $albums = sfFacebook::getFacebookApi("me/albums");
    //var_dump($albums['data']);
    
    foreach ($albums['data'] as $album)
    {
      if ($album['type'] == "profile")
      {
        $photo = sfFacebook::getFacebookApi($album['cover_photo']);
        //var_dump($photo);
        
        return $photo['source'];
      }
    }
    
    return false;
  }
}

