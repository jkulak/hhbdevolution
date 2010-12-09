<?php
/**
 * Song Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package hhbd
 **/

class Model_Song_Api extends Jkl_Model_Api
{
  
  static private $_instance;
  
  /**
   * Singleton instance
   *
   * @return Model_Song_Api
   */
  public static function getInstance()
  {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }

      return self::$_instance;
  }

  /**
   * Creates object and fetches the list from database result
   */
  private function _getList($query)
  {
    $result = $this->_db->fetchAll($query);;
    $list = new Jkl_List(); 
    foreach ($result as $params) {
      $list->add(new Model_Song_Container($params));
    }
    return $list;
  }
  
  public function find($id, $full = false)
  {
    $query = 'select *, id as song_id from songs where id=' . $id;
    $result = $this->_db->fetchAll($query);
    $params = $result[0];
    
    $params['featuring'] = Model_Artist_Api::getInstance()->getSongFeaturing($id);
    $params['music'] = Model_Artist_Api::getInstance()->getSongMusic($id);
    $params['scratch'] = Model_Artist_Api::getInstance()->getSongScratch($id);
    $params['artist'] = Model_Artist_Api::getInstance()->getSongArtist($id);
    
    $params['featured'] = Model_Album_Api::getInstance()->getSongAlbums($id, null);

    $max = sizeof($params['featured']);
    if ($max > 0) {
      for ($i=0; $i < $max; $i++) { 
        if ($params['featured']->items[$i]->artist != 'V/A') {
          $albumArtist = $params['featured']->items[$i]->artist;
          // we set artists for this song, so we can exit
          break(0);
        }
      }
    } else {
      // song is not featured on any album for some reason? send notification email
    }
    
    $params['albumArtist'] = $albumArtist;

    // assign album artist to a song, if it doesnt have artist assigned
    if (sizeof($params['artist']->items) < 1) {
      $params['artist']->add($albumArtist);
    }

    if ($full) {
      $searchTerms = (isset($albumArtist)?$albumArtist->name . ' ':'') . $params['title'];

      $rep = array('/', '&', '?', '-');
      $searchTerms = str_replace($rep, ' ', $searchTerms);

      $params['youTubeUrl'] = $this->_getYouTubeUrl($searchTerms);
    }

    $item = new Model_Song_Container($params);
    return $item;
  }
  
  public function getTracklist($id)
  {
    $query = 'SELECT t1.id as song_id, t2.track ' . 
        'FROM songs AS t1, album_lookup AS t2 ' .
        'WHERE (t1.id=t2.songid AND t2.albumid=' . $id . ') ' . 
        'ORDER BY t2.track';
    $result = $this->_db->fetchAll($query);
    $tracklist = new Jkl_List();
    foreach ($result as $params) {  
      $song = $this->find($params['song_id'], false);
      if (strlen($params['track']) > 2) {
        $song->track = substr($params['track'], 0, 1) . '-' . substr($params['track'], 1, 2);
      }
      else {
        $song->track = $params['track'];
      }
      $tracklist->add($song);
    }
    return $tracklist;
  }

  public function getAlbumDuration($id)
  {
    $query = 'SELECT sum(t1.length) as duration ' .
      'FROM songs AS t1, album_lookup AS t2 ' .
      'WHERE (t2.songid=t1.id AND t2.albumid=' . $id . ')';
    
//    $duration = Zend_Registry::get('Memcached')->load(md5($query));
    
    if (empty($duration)) {
      $result = $this->_db->fetchAll($query);
      if ($result[0]['duration'] > 0) {
        $duration = sprintf( "%02.2d:%02.2d", floor( $result[0]['duration'] / 60 ), $result[0]['duration'] % 60 );
      }
      else
      {
        $duration = 0;
      }
//      Zend_Registry::get('Memcached')->save($duration, md5($query));
    }
    return $duration;
  }

  public function getMostPopularByArtist($id, $limit = 10)
  {
    $query = 'SELECT *, t1.id as song_id
              FROM songs t1, artist_lookup t2, artists t3
              WHERE (t1.id=t2.songid AND t2.artistid=t3.id AND t3.id=' . $id . ')
              ORDER BY t1.viewed DESC
              ' . (($limit)?'LIMIT ' . $limit:'');
    return $this->_getList($query);    
  }
  
  public function getMostPopular($limit = 10)
  {
    $query = 'SELECT *, t1.id as song_id
              FROM songs t1
              ORDER BY t1.viewed DESC
              ' . (($limit)?'LIMIT ' . $limit:'');
    return $this->_getList($query);    
  }
  
  // This needs to be moved to separate counting system, not to kill datbase
  public function increaseViewed($id)
  {
    $query = 'UPDATE songs SET viewed=viewed+1 WHERE id=' . $id;
    $this->_db->query($query);
  }

  private function _getYouTubeUrl($searchTerms = 'polski hip-hop')
  {
    $mc = Zend_Registry::get('Memcached');
    $url = $mc->_cache->load(md5('clip' . $searchTerms));
    
    if (empty($url)) {
      $videoFeed = array();
      $yt = new Zend_Gdata_YouTube();
      $yt->setMajorProtocolVersion(2);
      $query = $yt->newVideoQuery();
      $query->setSafeSearch('none');
      $query->setVideoQuery($searchTerms);
      $query->setMaxResults(1);
      // print_r($query); die();
      $videoFeed = $yt->getVideoFeed($query->getQueryUrl(2));
    
      if (sizeof($videoFeed) < 1) {
        $words = explode(' ', $searchTerms);
        $retry = '';
        $max = sizeof($words) - 1;
        for ($i=0; $i < $max; $i++) { 
          $retry .= $words[$i] . ' ';
        }
        $query->setVideoQuery($retry);
        $videoFeed = $yt->getVideoFeed($query->getQueryUrl(2));
      }
      
      if (isset($videoFeed[0])) {
        $result = $videoFeed[0]->getFlashPlayerUrl();
        $mc->_cache->save(serialize($result), md5('clip' . $searchTerms));
      }
      else {
        // after two attempts nothig was found
        $result = false;
      }
    } else {
      $result = unserialize($url);
    }
    return $result;
  }
}