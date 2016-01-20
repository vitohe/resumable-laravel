<?php
namespace Dilab\Network;

use Dilab\Network\Response;

class SimpleResponse implements Response
{
    /**
     * @param $statusCode
     * @return mixed
     */
    public function header($statusCode)
    {
        if (200==$statusCode) {
            return true;
        } else if (204==$statusCode) {
            return false;
        } else if (404==$statusCode) {
            return false;
        }
        return false;
    }

}
