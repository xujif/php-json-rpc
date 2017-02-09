<?php
/**
 * Created by xujif.
 * User: i
 * Date: 2017/2/8 0008
 * Time: 19:05
 */

namespace Xujif\JsonRpc;

use GuzzleHttp\Client as HttpClient;


class Client
{
    protected $httpclient;
    protected $severUrl;
    protected $batchMode = false;
    protected $notifyMode = false;
    protected $payload = [];

    public function __construct($serverUrl, $headers = [], $debug = false)
    {
        $this->serverUrl = $serverUrl;
        $this->httpclient = new HttpClient(['debug' => $debug, 'headers' => $headers]);
    }

    public function batch()
    {
        $this->batchMode = true;
        return $this;
    }

    public function notify($notify = true)
    {
        $this->notifyMode = $notify;
        return $this;
    }

    public function exec()
    {
        $isBatch = $this->batchMode;
        $this->batchMode = false;
        $async = true;
        if ($isBatch) {
            foreach ($this->payload as $req) {
                if (isset($req['id'])) {
                    $async = true;
                    break;
                }
            }
        } else {
            $async = isset($this->payload['id']);
        }
        if ($async) {
            $this->httpclient->postAsync($this->serverUrl, [
                'json' => $this->payload,
            ]);
            return;
        } else {
            $res = $this->httpclient->post($this->serverUrl, [
                'json' => $this->payload,
            ]);
        }
        if ($res->getStatusCode() != 200) {
            throw new \BadMethodCallException('remote rpc not return 200');
        }
        $json = (string)$res->getBody();
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \BadMethodCallException('parse result error');
        }
        if ($isBatch) {
            return array_map(function ($arr) use ($json) {
                if (isset($arr['error'])) {
                    throw new \BadMethodCallException($json);
                }
                return $arr['result'];
            }, $data);
        } else {
            if (isset($data['error'])) {
                throw new \BadMethodCallException($json);
            }
            return $data['result'];
        }
    }


    public function call($remoteMethod, $args)
    {
        if ($this->batchMode) {
            if ($this->notifyMode) {
                $this->payload[] = [
                    'method' => $remoteMethod,
                    'params' => $args,
                    'jsonrpc' => '2.0'
                ];
            } else {
                $this->payload[] = [
                    'id' => mt_rand(100000, 999999),
                    'method' => $remoteMethod,
                    'params' => $args,
                    'jsonrpc' => '2.0'
                ];
            }
            return $this;
        } else {
            if ($this->notifyMode) {
                $this->payload = [
                    'method' => $remoteMethod,
                    'params' => $args,
                    'jsonrpc' => '2.0'
                ];
            } else {
                $this->payload = [
                    'id' => mt_rand(100000, 999999),
                    'method' => $remoteMethod,
                    'params' => $args,
                    'jsonrpc' => '2.0'
                ];
            }
            return $this->exec();
        }
    }

    public function __call($method, $args)
    {
        return $this->call($method, $args);
    }
}