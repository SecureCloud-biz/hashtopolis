<?php

/**
 * Handles the login sessions
 *
 * @author Sein
 */
class Login {
  private $user    = null;
  private $valid   = false;
  private $session = null;
  
  public function getLevel() {
    global $FACTORIES;
    
    if ($this->valid) {
      $rightGroup = $FACTORIES::getRightGroupFactory()->get($this->user->getRightGroupId());
      return $rightGroup->getLevel();
    }
    return 0;
  }
  
  /**
   * Creates a Login-Instance and checks automatically if there is a session
   * running. It updates the session lifetime again up to the session limit.
   */
  public function __construct() {
    global $FACTORIES;
    
    $this->user = null;
    $this->session = null;
    $this->valid = false;
    if (isset($_COOKIE['session'])) {
      $session = $_COOKIE['session'];
      $filter1 = new QueryFilter("sessionKey", $session, "=");
      $filter2 = new QueryFilter("isOpen", "1", "=");
      $filter3 = new QueryFilter("lastActionDate", time() - 10000, ">");
      $check = $FACTORIES::getSessionFactory()->filter(array('filter' => array($filter1, $filter2, $filter3)));
      if ($check === null || sizeof($check) == 0) {
        setcookie("session", "", time() - 600); //delete invalid or old cookie
        return;
      }
      $s = $check[0];
      $this->user = $FACTORIES::getUserFactory()->get($s->getUserId());
      if ($this->user !== null) {
        if ($s->getLastActionDate() < time() - $this->user->getSessionLifetime()) {
          setcookie("session", "", time() - 600); //delete invalid or old cookie
          return;
        }
        $this->valid = true;
        $this->session = $s;
        $s->setLastActionDate(time());
        $FACTORIES::getSessionFactory()->update($s);
        setcookie("session", $s->getSessionKey(), time() + $this->user->getSessionLifetime());
      }
    }
  }
  
  /**
   * Returns true if the user currently is loggedin with a valid session
   */
  public function isLoggedin() {
    return $this->valid;
  }
  
  /**
   * Logs the current user out and closes his session
   */
  public function logout() {
    global $FACTORIES;
    
    $this->session->setIsOpen(0);
    $FACTORIES::getSessionFactory()->update($this->session);
    setcookie("session", "", time() - 600);
  }
  
  /**
   * Returns the uID of the currently logged in user, if the user is not logged
   * in, the uID will be -1
   */
  public function getUserID() {
    return $this->user->getId();
  }
  
  public function getUser() {
    return $this->user;
  }
  
  /**
   * Executes a login with given username and password (plain)
   *
   * @param string $user username of the user to be logged in
   * @param string $password password which was entered on login form
   * @return true on success and false on failure
   */
  public function login($username, $password) {
    global $FACTORIES;
    
    if ($this->valid == true) {
      return false;
    }
    $filter = new QueryFilter("username", $username, "=");
    $check = $FACTORIES::getUserFactory()->filter(array('filter' => array($filter)));
    if ($check === null || sizeof($check) == 0) {
      return false;
    }
    $user = $check[0];
    if ($user->getIsValid() != 1) {
      return false;
    }
    else if (!Encryption::passwordVerify($user->getUsername(), $password, $user->getPasswordSalt(), $user->getPasswordHash())) {
      return false;
    }
    $this->user = $user;
    $startTime = time();
    $s = new Session(0, $this->user->getId(), $startTime, $startTime, 1, $this->user->getSessionLifetime(), "");
    $s = $FACTORIES::getSessionFactory()->save($s);
    if ($s === null) {
      return false;
    }
    $sessionKey = Encryption::sessionHash($s->getId(), $startTime, $user->getEmail());
    $s->setSessionKey($sessionKey);
    $FACTORIES::getSessionFactory()->update($s);
    
    $this->user->setLastLoginDate(time());
    $FACTORIES::getUserFactory()->update($this->user);
    
    $this->valid = true;
    setcookie("session", "$sessionKey", time() + $this->user->getSessionLifetime());
    return true;
  }
}



