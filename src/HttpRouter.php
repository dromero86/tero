<?php 

namespace Tero;

use Exception;

class HttpRouter{

    public function getRouter(){
        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            $protocolo = $_SERVER['SERVER_PROTOCOL'];
            if (strpos($protocolo, 'HTTP/2') !== false) {
                return new Http2Router();
            } elseif (strpos($protocolo, 'HTTP/1.1') !== false) {
                return new Http1Router();
            } else {
                throw new Exception("Unknown http protocol definition");
            }
        } else {
            throw new Exception("Http protocol number not found");
        }
    }

}