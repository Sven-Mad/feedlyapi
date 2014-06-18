<?php

class FeedlyAPI {
    private
        $_Path              = array('apiBaseUrl'        => 'https://cloud.feedly.com',
                                    'authorize'         => '/v3/auth/auth',
                                    'accessToken'       => '/v3/auth/token',

                                    'subscriptions'     => '/v3/subscriptions',
                                    'categories'        => '/v3/categories',
                                    'tags'              => '/v3/tags',
                                    'markers'           => '/v3/markers',
                                    'feeds'             => '/v3/feeds',
                                    'streams'           => '/v3/streams',
                                    'entries'           => '/v3/entries',
                                   ),
        $_sandbox           = false,
        $_user              = array();

    public function __construct($_user = null, $_sandbox=null)
    {
        if (is_bool($_sandbox) &&
            $_sandbox
        ) {
            $this->_sandbox = true;
            $this->_Path['apiBaseUrl'] = 'https://sandbox.feedly.com';
        }

        if (is_array($_user)) {
            foreach ($_user as $_key => $_val) {
                $this->$_key = $_val;
            }
        }
    }

    public function __set($name, $value)
    {
        $this->_user[$name] = $value;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->_user)) {
            return $this->_user[$name];
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    public function __isset($name)
    {
        return isset($this->_user[$name]);
    }

    public function __unset($name)
    {
        unset($this->_user[$name]);
    }

    private function _Exec() {
        $r              = null;
        $_curl_infos    = null;
        $url            = $this->_Path['apiBaseUrl'];

        if (1 == func_num_args()) {
            $_curl_infos = func_get_arg(0);
        }
        if(isset($_curl_infos['url']) &&
           '' != trim($_curl_infos['url'])
        ) {
            $url .= $_curl_infos['url'];
        }

        if(isset($_curl_infos['get']) &&
           is_array($_curl_infos['get'])
        ) {
            $url = $url . '?' . http_build_query($_curl_infos['get']);
            $_curl_infos['header'] = 'GET';
        }

        if (($r = @curl_init($url)) == false) {
            throw new Exception("Cannot initialize cUrl session.
                Is cUrl enabled for your PHP installation?");
        }

        if((isset($_curl_infos['header']) &&
            'post' == $_curl_infos['header']
           )||
           isset($_curl_infos['post'])
        ) {
            if (!isset($_curl_infos['header'])) {
                $_curl_infos['header'] = 'POST';
            }
            curl_setopt($r, CURLOPT_POST, true);
            if (isset($_curl_infos['post'])) {
                if (is_array($_curl_infos['post'])) {
                    $_curl_infos['post'] = http_build_query($_curl_infos['post']);
                }
                curl_setopt($r, CURLOPT_POSTFIELDS, $_curl_infos['post']);
            }
        }
        if (!isset($_curl_infos['header'])) {
            $_curl_infos['header'] = 'GET';
        }
        curl_setopt($r, CURLOPT_CUSTOMREQUEST, STRTOUPPER($_curl_infos['header']));

        curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
        if (isset($_curl_infos['encoding'])) {
            curl_setopt($r, CURLOPT_ENCODING, $_curl_infos['encoding']);
        } else {
            curl_setopt($r, CURLOPT_ENCODING, "");
        }

        curl_setopt($r, CURLOPT_SSL_VERIFYPEER, false);

        if (!isset($_curl_infos['send'])) {
            $_curl_infos['send'] = '';
        }
        switch ($_curl_infos['send']) {
            case 'basic':
                $_curl_infos['headers'][] = 'Authorization: Basic '.base64_encode($this->client_id.':'.$this->client_secret);
                break;
            case 'none':
                break;
            default:
                $_curl_infos['headers'][] = 'Authorization: OAuth '.$this->access_token;
                break;
        }

        if (isset($_curl_infos['headers']) &&
            is_array($_curl_infos['headers'])
        ) {
            curl_setopt($r, CURLOPT_HTTPHEADER, $_curl_infos['headers']);
        }
        $response = curl_exec($r);
        $http_status = curl_getinfo($r, CURLINFO_HTTP_CODE);
        curl_close($r);

        if($http_status!==200) {
            $tmpr = json_decode($response, true);
            throw new Exception("Response from API: " . $tmpr['errorMessage']);

        }
        return $response;
    }

    public function checkToken() {

        if (true == isset($this->_user['access_token'])) {
            if (time() > $this->_user['last_token'] + $this->_user['expires_in']) {
                $_token = json_decode($this->_refreshAccessToken(), true);
                $this->_user = array_merge($this->_user, $_token);
            }
            return true;
        }
    }

    private function _refreshAccessToken() {
        $request = array('url'       => $this->_Path['accessToken'],
                         'post'      => ARRAY('refresh_token'    => $this->refresh_token,
                                              'client_id'        => $this->clientID,
                                              'client_secret'    => $this->secret,
                                              'grant_type'       => 'refresh_token',
                                             ),
                         'send'      => 'Basic',
                        );
        $response = $this->_Exec($request);
        return $response;
    }

    // Subscriptions

    public function getSubscriptions() {
        $request = array('url'  => $this->_Path['subscriptions'],
                        );
        return $this->_Exec($request);
    }

    public function setSubscription($_feed = null, $opts = null) {
        if (!is_null($_feed) &&
            !empty($_feed) &&
            '' != trim($_feed)
        ) {
            $_feed = trim($_feed);
            $r = null;
            if (($r = @curl_init($_feed)) == false) {
                throw new Exception("Cannot initialize cUrl session.
                    Is cUrl enabled for your PHP installation?");
            }
            curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($r, CURLOPT_FOLLOWLOCATION, true);

            curl_setopt($r, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($r);
            $http_status = curl_getinfo($r, CURLINFO_HTTP_CODE);
            curl_close($r);

            if (200  == $http_status &&
                true == preg_match('#'.preg_quote('<?xml').'#i', $response)
            ) {
                if (is_null($opts)) {
                    $opts = array();
                }
                $opts = array_merge($opts, array('id'   => 'feed/'.$_feed
                                                )
                                   );
                if (isset($opts['categories']) &&
                    is_array($opts['categories'])
                ) {
                    $_categories = $opts['categories'];
                    unset($opts['categories']);
                    $opts['categories'] = array();
                    foreach ($_categories as $_key => $_name) {
                        $opts['categories'][] = array('id'      => 'user/'.$this->id.'/category/'.PREG_REPLACE('/([^A-Za-z0-9_.-])/', '', $_name),
                                                      'label'   => $_name,
                                                     );

                    }
                }
                $request = array('url'      => $this->_Path['subscriptions'],
                                 'post'     => json_encode($opts
                                                          ),
                                 'headers'  => array('Content-Type: application/json'),
                                );
                $this->_Exec($request);
                return true;
            }
        }
        return false;
    }

    public function deleteSubscription($_feed = null) {
        if (!is_null($_feed) &&
            !empty($_feed) &&
            '' != trim($_feed)
        ) {
            $_feed = trim($_feed);
            $request = array('url'      => $this->_Path['subscriptions'].'/'.urlencode($_feed),
                             'header'   => 'delete',
                            );
            $this->_Exec($request);
            return true;
        }
        return false;
    }


    // categories
    public function getCategories() {
        $request = array('url'  => $this->_Path['categories'],
                        );
        return $this->_Exec($request);
    }

    public function updateCategory($_name_cur = null, $_name_new = null) {
        if (!is_null($_name_cur) &&
            !empty($_name_cur) &&
            '' != trim($_name_cur) &&
            !is_null($_name_new) &&
            !empty($_name_new) &&
            '' != trim($_name_new)
        ) {
            $cur_categories = json_decode($this->getCategories(), true);
            if (0 < sizeof($cur_categories) &&
                is_array($cur_categories)
            ) {
                foreach ($cur_categories as $_key => $_infos) {
                    if ($_infos['label'] == $_name_cur ||
                        $_infos['id']    == $_name_cur
                    ) {
                        $_name_new = trim($_name_new);
                        $request = array('url'      => $this->_Path['categories'].'/'.urlencode($_infos['id']),
                                         'post'     => json_encode(array('label'    => $_name_new)),
                                         'headers'  => array('Content-Type: application/json'),
                                        );
                        $this->_Exec($request);
                        return true;
                        break;
                    }
                }
            }
        }
        return false;
    }

    public function deleteCategory($_name = null) {
        if (!is_null($_name) &&
            !empty($_name) &&
            '' != trim($_name)
        ) {
            $cur_categories = json_decode($this->getCategories(), true);
            if (0 < sizeof($cur_categories) &&
                is_array($cur_categories)
            ) {
                foreach ($cur_categories as $_key => $_infos) {
                    if ($_infos['label'] == $_name) {
                        $request = array('url'      => $this->_Path['categories'].'/'.urlencode($_infos['id']),
                                         'header'   => 'delete',
                                        );
                        $this->_Exec($request);
                        return true;
                        break;
                    }
                }
            }
        }
        return false;
    }

    // Markers
    public function getUnread($_streamID = null, $_offtime = null, $_autorefresh = false) {
        $opts = array();
        if (!is_null($_offtime) &&
            is_numeric($_offtime) &&
            0 < $_offtime &&
            60*60*24*30 > $_offtime
        ) {
            $opts['newerThan'] = time()-$_offtime.'000';
        }
        if (!is_bool($_autorefresh)) {
            $_autorefresh = false;
        }
        $opts['autorefresh'] = $_autorefresh;

        if (!is_null($_streamID) &&
            !empty($_streamID) &&
            '' != trim($_streamID) &&
            'feed/' == substr($_streamID, 0, 5)
        ) {
            $opts['streamId'] = $_streamID;
        }
        $request = array('url'  => $this->_Path['markers'].'/counts',
                         'get'  => $opts,
                        );
        return $this->_Exec($request);
    }

    private function setMarkerRead($_action = 'markAsRead', $_what = null, $_last_read = null) {
        if (!is_null($_what)) {
            if (!is_array($_what)) {
                $_what = array($_what);
            }
            $_todo = array();
            foreach ($_what as $_key => $_entry) {
                if ('feed/' == substr($_entry, 0, 5)) {
                    $_todo['feeds'][] = $_entry;
                    continue;
                }
                if ('user/'.$this->id.'/categories/' == substr($_entry, 0, strlen('user/'.$this->id.'/categories/'))) {
                    $_todo['categories'][] = $_entry;
                    continue;
                }
                $_todo['entries'][] = $_entry;
            }

            if (0 < sizeof($_todo)) {
                if (true == isset($_todo['feeds'])) {
                    $opt = array('action'       => $_action,
                                 'type'         => 'categories',
                                 'categoryIds'  => $_todo['entryIds']
                                );
                    if ('markAsRead' == $_action) {
                        if (is_null($_last_read)) {
                            return false;
                        }
                        if (is_numeric($_last_read)) {
                            $opt['asOf'] = $_last_read.'000';
                        } else {
                            $opt['lastReadEntryId'] = $_last_read;
                        }
                    }
                    $request = array('url'      => $this->_Path['markers'],
                                     'post'     => json_encode($opt),
                                     'headers'  => array('Content-Type: application/json'),
                                    );
                    return $this->_Exec($request);
                }

                if (true == isset($_todo['categories'])) {

                    $opt = array('action'   => $_action,
                                 'type'     => 'feeds',
                                 'feedIds'  => $_todo['entryIds']
                                );
                    if ('markAsRead' == $_action) {
                        if (is_null($_last_read)) {
                            return false;
                        }
                        if (is_numeric($_last_read)) {
                            $opt['asOf'] = $_last_read.'000';
                        } else {
                            $opt['lastReadEntryId'] = $_last_read;
                        }
                    }
                    $request = array('url'      => $this->_Path['markers'],
                                     'post'     => json_encode($opt),
                                     'headers'  => array('Content-Type: application/json'),
                                    );
                    return $this->_Exec($request);
                }

                if (true == isset($_todo['entries'])) {
                    $opt = array('action'   => $_action,
                                 'type'     => 'entries',
                                 'entryIds' => $_todo['entries']
                                );
                    if ('undoMarkAsRead' == $_action) {
                        return false;
                    }
                    $request = array('url'      => $this->_Path['markers'],
                                     'post'     => json_encode($opt),
                                     'headers'  => array('Content-Type: application/json'),
                                    );
                    return $this->_Exec($request);
                }
            }
        }
    }

    public function setRead($_what = null, $_last_read = null) {
        return $this->setMarkerRead('markAsRead', $_what, $_last_read);
    }

    public function setUndoRead($_what = null) {
        return $this->setMarkerRead('undoMarkAsRead', $_what);
    }

    public function setUnRead($_what = null) {
        return $this->setMarkerRead('keepUnread', $_what);
    }

    public function getLatestRead($_newerthan = null) {
        $opt = array();
        if (!is_null($_newerthan) &&
            is_numeric($_newerthan)
        ) {
            $opt['newerThan'] = $_newerthan.'000';
        }
        $request = array('url'      => $this->_Path['markers'].'/reads',
                         'get'      => $opt,
                        );
        return $this->_Exec($request);
    }

    public function getLatestTagged($_newerthan = null) {
        $opt = array();
        if (!is_null($_newerthan) &&
            is_numeric($_newerthan)
        ) {
            $opt['newerThan'] = $_newerthan.'000';
        }
        $request = array('url'      => $this->_Path['markers'].'/tags',
                         'get'      => $opt,
                        );
        return $this->_Exec($request);
    }

    // Tags
    public function getTags() {
        $request = array('url'  => $this->_Path['tags'],
                        );
        return $this->_Exec($request);
    }

    public function setTags($_tags = null, $_entry = null) {
        if (!is_null($_tags) &&
            !is_null($_entry)
        ) {
            $opt = array();
            if (!is_array($_tags)) {
                $_tags = array($_tags);
            }
            foreach ($_tags as $_key => $_tag) {
                if ('user/'.$this->id.'/tag/' != substr($_tag, 0, strlen('user/'.$this->id.'/tag/'))) {
                    $_tags[$_key] = 'user/'.$this->id.'/tag/'.PREG_REPLACE('/([^A-Za-z0-9_.-])/', '', $_tag);
//                    $opt['label'] = $_tag;
                }
            }
            if (!is_array($_entry)) {
                $opt['entryId'] = $_entry;
            } else {
                $opt['entryIds'] = $_entry;
            }
            $request = array('url'      => $this->_Path['tags'].'/'.implode(',', array_map('urlencode', $_tags)),
                             'post'     => json_encode($opt),
                             'headers'  => array('Content-Type: application/json'),
                             'header'   => 'PUT',
                            );
print_r($request);
            return $this->_Exec($request);
        }
        return false;
    }

    public function setUnTag($_tags = null, $_entry = null) {
        if (!is_null($_tags) &&
            !is_null($_entry)
        ) {
            if (!is_array($_tags)) {
                $_tags = array($_tags);
            }
            if (!is_array($_entry)) {
                $_entry = array($_entry);
            }
            $request = array('url'      => $this->_Path['tags'].'/'.implode(',', array_map('urlencode', $_tags)).'/'.implode(',', array_map('url_encode', $_entry)),
                             'header'   => 'delete',
                            );

            return $this->_Exec($request);
        }
        return false;
    }

    public function deleteTag($_tags = null) {
        if(!is_null($_tags)) {
            if (!is_array($_tags)) {
                $_tags = array($_tags);
            }
            $cur_tags = json_decode($this->getTags(), true);
            if (0 < sizeof($cur_tags) &&
                is_array($cur_tags)
            ) {
                foreach ($cur_tags as $_key => $_infos) {
                    foreach ($_tags as $_tag_key => $_tag) {
                        if ($_infos['label'] == $_tag ||
                            $_infos['id']    == $_tag
                        ) {
                            $_tags[$_tag_key] = $_infos['id'];
                            break;
                        }
                    }
                }
                $request = array('url'      => $this->_Path['tags'].'/'.implode(',', array_map('urlencode', $_tags)),
                                 'header'   => 'delete',
                                );
                return $this->_Exec($request);
            }
        }
        return false;
    }

    public function updateTag($_name_cur = null, $_name_new = null) {
        if (!is_null($_name_cur) &&
            !empty($_name_cur) &&
            '' != trim($_name_cur) &&
            !is_null($_name_new) &&
            !empty($_name_new) &&
            '' != trim($_name_new)
        ) {
            $cur_tags = json_decode($this->getTags(), true);
            if (0 < sizeof($cur_tags) &&
                is_array($cur_tags)
            ) {
                foreach ($cur_tags as $_key => $_infos) {
                    if ($_infos['label'] == $_name_cur ||
                        $_infos['id']    == $_name_cur
                    ) {
                        $_name_new = trim($_name_new);
                        $request = array('url'      => $this->_Path['tags'].'/'.urlencode($_infos['id']),
                                         'post'     => json_encode(array('label'    => $_name_new)),
                                         'headers'  => array('Content-Type: application/json'),
                                        );
                        $this->_Exec($request);
                        return true;
                        break;
                    }
                }
            }
        }
        return false;
    }

    // Stream
    public function getStream($_streamId = null, $_opts = null) {
        if (!is_null($_streamId) &&
            !empty($_streamId) &&
            ('feed/' == substr($_streamId, 0, 5) ||
             'user/'.$this->id.'/categories/' == substr($_entry, 0, strlen('user/'.$this->id.'/categories/')) ||
             'user/'.$this->id.'/tag/' == substr($_entry, 0, strlen('user/'.$this->id.'/tags/'))
            )
        ) {
            $opts = array();
            $opts['type'] = 'ids';
            if (!is_null($_opts) &&
                is_array($_opts)
            ) {
                if (isset($_opts['type']) &&
                    ('contents' == $_opts['type'] ||
                     'ids'      == $_opts['type']
                    )
                ) {
                    $opts['type'] = $_opts['type'];
                }
                if (isset($_opts['ranked']) &&
                    ('newest'   == $_opts['ranked'] ||
                     'oldest'   == $_opts['ranked']
                    )
                ) {
                    $opts['ranked'] = $_opts['ranked'];
                }
                if (isset($_opts['unreadonly']) &&
                    is_bool($_opts['unreadonly']) &&
                    true == $_opts['unreadonly']
                ) {
                    $opts['unreadOnly'] = 'true';
                }
                if (isset($_opts['newerthan']) &&
                    is_numeric($_opts['newerthan'])
                ) {
                    $opts['newerThan'] = $_opts['newerthan'].'000';
                }
                if (isset($_opts['count']) &&
                    is_numeric($_opts['count'])
                ) {
                    $opts['count'] = $_opts['count'];
                }
                if (isset($_opts['continuation'])) {
                    $opts['continuation'] = $_opts['continuation'];
                }
            }

            $request = array('url'      => $this->_Path['streams'].'/'.urlencode($_streamId).'/'.$opts['type'],
                             'get'      => $opts,
                            );
            $response = $this->_Exec($request);
            if ('contents' == $opts['type']) {
                $_response = json_decode($response, true);
                if (isset($_response['items'])) {
                    foreach ($_response['items'] as $_key => $_infos) {
                        $_response['ids'][] = $_infos['ids'];
                    }
                }
                $response = json_encode($_response);
            }
            return $response;
        }
        return false;
    }


    public function getEntry($_entryId = null) {
        if (!is_null($_entryId) &&
            !empty($_entryId) &&
            '' != trim($_entryId)
        ) {
            $request = array('url'      => $this->_Path['entries'].'/'.urlencode($_entryId),
                            );
            return $this->_Exec($request);

        }
        return false;
    }

    // Feed
    public function getFeedInfos($_from = null) {
        if (!is_null($_from)) {
            if (!is_array($_from)) {
                $_from = array($_from);
            }
            $request = array('url'      => $this->_Path['feeds'].'/.mget',
                             'post'     => json_encode($_from),
                             'headers'  => array('Content-Type: application/json'),
                            );
            return $this->_Exec($request);

        }
        return false;
    }
}
?>
