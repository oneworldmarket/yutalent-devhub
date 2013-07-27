<?php

require_once 'oauth/http.php';
require_once 'oauth/oauth_client.php';

class WU_API
{
    protected $_frame;
    protected $_url;
    protected $_token;
    protected $_request;
    protected $_params;

    protected $_oauth;

    public function __construct()
    {
        if( !session_id() )
        {
            session_start();
        }

        $data = $this->parseSignedRequest();

        if( count($data) )
        {
            $this->_frame = $data['frame'];
            $this->_url = $data['url'];
            $this->_token = $data['token'];
            $this->_request = $data['request'];
            $this->_params = $_POST + $data['params'];
        }

        $this->_oauth = new oauth_client_class();

        $this->_oauth->offline = true;
        $this->_oauth->request_token_url = WU_DOMAIN . '/c/oauth/authorize?client_id={CLIENT_ID}&redirect_uri={REDIRECT_URI}&scope={SCOPE}&state={STATE}';
        $this->_oauth->dialog_url = WU_DOMAIN . '/c/oauth/authorize?client_id={CLIENT_ID}&redirect_uri={REDIRECT_URI}&scope={SCOPE}&state={STATE}';

        $this->_oauth->access_token_url = WU_DOMAIN . '/c/oauth/access_token';

        $this->_oauth->debug = true;
        $this->_oauth->debug_http = true;
        $this->_oauth->server = '';

        $this->_oauth->redirect_uri = WU_DOMAIN . $_SERVER['REQUEST_URI'];

        $this->_oauth->client_id = WU_ID;
        $this->_oauth->client_secret = WU_SECRET;

        $this->_oauth->scope = '';

        $this->_oauth->session_started = true;

        $this->_oauth->Initialize();
    }

    public function setRedirectUri( $uri )
    {
        $this->_oauth->redirect_uri = $uri;
    }

    public function setToken( $token )
    {
        $this->_token = $token;
    }

    public function setClientId( $id )
    {
        $this->_oauth->client_id = $id;
    }

    public function setClientSecret( $secret )
    {
        $this->_oauth->client_secret = $secret;
    }

    protected function parseSignedRequest()
    {
        if( isset($_POST['signed_request']) )
        {
            //decode it

            list($crc32, $payloadEncoded) = explode('.', $_POST['signed_request']);

            $computedCrc32 = hash('crc32', $payloadEncoded);

            if( $crc32 !== $computedCrc32 )
            {
                throw new Exception('Invalid checksum');
            }

            $payload = json_decode( base64_decode( $payloadEncoded ), true);

            return $payload;
        }

        return array();
    }

    public function sendMessageToWU($method, $params = array())
    {
        $params['method'] = $method;

        if( $this->getToken() )
        {
            $this->_oauth->StoreAccessToken( array( 'value' => $this->getToken() ) );
        }

        $success = $this->_oauth->Process();
        $success = $this->_oauth->Finalize($success);

        if( $success )
        {
            $success = $this->_oauth->CallAPI(
                WU_DOMAIN . '/c/oauth/v1?' . http_build_query($params),
                'GET', array(), array('FailOnAccessError'=>true), $response);

            if( !$success )
            {
                $this->_oauth->Output();
                die('Error calling the API');
            }
            else
            {
                return $response;
            }
        }
        else
        {
            $this->_oauth->Output();
            die('Authentication error');
        }
    }

    public function getAllParams()
    {
        return $this->_params;
    }

    public function getParam( $key )
    {
        return $this->_params[ $key ];
    }

    public function getFrameId()
    {
        return $this->_frame;
    }

    public function getToken()
    {
        return $this->_token;
    }

    public function getUrl()
    {
        return $this->_url;
    }
}