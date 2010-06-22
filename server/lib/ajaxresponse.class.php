<?php
/**
 * Prepare raw data to AJAX response.
 * 
 * @package stalker_portal
 * @author zhurbitsky@gmail.com
 */

class AjaxResponse
{
    
    protected $db;
    protected $stb;
    protected $page = 0;
    protected $load_last_page = false;
    protected $response = array(
                    'total_items'    => 0,
                    'max_page_items' => MAX_PAGE_ITEMS,
                    'selected_item'  => 0,
                    'cur_page'       => 0,
                    'data'           => array(),
                );
    
    protected $all_abc = array(
        'RU' => array('*','А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'),
        'EN' => array('*','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','W','Z')
    );
    
    protected $all_months = array(
        'RU' => array(
                    'январь',
                    'февраль',
                    'март',
                    'апрель',
                    'май',
                    'июнь',
                    'июль',
                    'август',
                    'сентябрь',
                    'октябрь',
                    'ноябрь',
                    'декабрь'
                ),
        'EN' => array()
    );
    
    protected $abc = array();
    protected $months = array();
    
    protected function __construct(){
        
        $this->abc = $this->all_abc[LANG];
        $this->months = $this->all_months[LANG];
        
        $this->db  = Mysql::getInstance();
        $this->stb = Stb::getInstance();
        
        $this->page = @intval($_REQUEST['p']);
        
        if ($this->page == 0){
            $this->load_last_page = true;
        }
        
        if ($this->page > 0){
            $this->page--;
        }
    }
    
    protected function setResponse($key, $value){
        $this->response[$key] = $value;
    }
    
    protected function setResponseData(Mysql $query){
        
        $query_rows = clone $query;
        
        $this->setResponse('total_items', $query_rows->nolimit()->noorderby()->count()->get()->counter());
        $this->setResponse('data', $query->get()->all());
    }
    
    protected function getResponse($callback = ''){
        
        if ($callback && is_callable(array($this, $callback))){
            return call_user_func(array($this, $callback));
        }
        
        return $this->response;
    }
    
    protected function getImgUri($id){
    
        $dir_name = ceil($id/FILES_IN_DIR);
        $dir_path = IMG_URI.$dir_name;
        $dir_path .= '/'.$id.'.jpg';
        return $dir_path;
    }
    
    protected function setClaimGlobal($media_type){
        
        $id   = intval($_REQUEST['id']);
        $type = $_REQUEST['real_type'];
        
        $this->db->insert('media_claims_log',
                          array(
                              'media_type' => $media_type,
                              'media_id'   => $id,
                              'type'       => $type,
                              'uid'        => $this->stb->id,
                              'added'      => 'NOW()'
                          ));
                          
        $total_media_claims = $this->db->from('media_claims')->where(array('media_type' => $media_type, 'media_id' => $id))->get()->first();
        
        $sound_counter = 0;
        $video_counter = 0;
        
        if ($type == 'video'){
            $video_counter++;
        }else{
            $sound_counter++;
        }
        
        if (!empty($total_media_claims)){
            $this->db->update('media_claims',
                              array(
                                  'sound_counter' => $total_media_claims['sound_counter'] + $sound_counter,
                                  'video_counter' => $total_media_claims['video_counter'] + $video_counter,
                              ),
                              array(
                                  'media_type' => $media_type,
                                  'media_id'   => $id
                              ));
        }else{
            $this->db->insert('media_claims',
                              array(
                                  'sound_counter' => $sound_counter,
                                  'video_counter' => $video_counter,
                                  'media_type'    => $media_type,
                                  'media_id'      => $id
                              ));
        }
        
        $total_daily_claims = $this->db->from('daily_media_claims')->where(array('date' => 'CURDATE()'))->get()->first();
        
        if (!empty($total_daily_claims)){
            $this->db->update('daily_media_claims',
                              array(
                                  $media_type.'_sound' => $total_daily_claims[$media_type.'_sound'] + $sound_counter,
                                  $media_type.'_video' => $total_daily_claims[$media_type.'_video'] + $video_counter
                              ),
                              array('date' => 'CURDATE()'));
        }else{
            $this->db->insert('daily_media_claims',
                              array(
                                  $media_type.'_sound' => $sound_counter,
                                  $media_type.'_video' => $video_counter,
                                  'date'               => 'CURDATE()'
                              ));
        }
    }
}

?>