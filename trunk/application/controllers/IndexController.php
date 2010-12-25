<?php

class IndexController extends Zend_Controller_Action
{
    public function init()
    {
      $this->view->headMeta()->setName('keywords', 'polski hip-hop, albumy');
      $this->view->headTitle()->headTitle('Hhbd.pl - Hip-hopowa baza danaych', 'SET');
      $this->view->headMeta()->setName('description', 'Albumy w hhbd.pl');
    }

    public function indexAction()
    {
      $this->view->news = Model_News_Api::getInstance()->getRecent(8);
      $this->view->newestList = Model_Album_Api::getInstance()->getNewest(5);
      $this->view->announcedList = Model_Album_Api::getInstance()->getAnnounced(5);
      
      $artist = Model_Artist_Api::getInstance()->find(6, true);
      $artist->addPopularSongs(Model_Song_Api::getInstance()->getMostPopularByArtist($artist->id, 4, true));
      $this->view->mainArtist = $artist;
      
      $this->view->popularSongs = Model_Song_Api::getInstance()->getMostPopular(15);
      $this->view->popularArtists = Model_Artist_Api::getInstance()->getMostPopular(10);
      $this->view->popularAlbums = Model_Album_Api::getInstance()->getPopular(5);
    }
    
    public function aboutAction()
    {
    }
    
    public function contactAction()
    {
    }
}