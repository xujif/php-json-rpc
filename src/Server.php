<?php
/**
 * Created by xujif.
 * User: i
 * Date: 2017/2/8 0008
 * Time: 18:38
 */

namespace Xujif\JsonRpc;

class Server
{
    protected $apiObject;

    protected $handleExceptions = [];

    public function __construct($obj)
    {
        $this->apiObject = $obj;
    }

    public function withExceptionsToError(array $exceptions = [])
    {
        $this->handleExceptions = $exceptions;
    }

    protected function processMethod($request)
    {

        if (
            !isset($request['method']) ||
            !is_string($request['method'])
        ) {
            return [
                'id' => null,
                'jsonrpc' => "2.0",
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request'
                ],
            ];
        }
        if (!method_exists($this->apiObject, $request['method']) && !method_exists($this->apiObject, $request['__call'])) {
            return [
                'id' => null,
                'jsonrpc' => "2.0",
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found'
                ]
            ];
        }
        if (isset($request['params']) && !is_array($request['params'])) {
            return [
                'id' => null,
                'jsonrpc' => "2.0",
                'error' => [
                    'code' => -32602,
                    'message' => 'Method not found'
                ]
            ];
        }
        try {
            $ret = call_user_func_array([$this->apiObject, $request['method']], isset($request['params']) ? $request['params'] : []);
            if (isset($request['id'])) {
                return [
                    'id' => $request['id'],
                    'jsonrpc' => "2.0",
                    'result' => $ret,
                ];
            } else {
                return [
                    'jsonrpc' => "2.0",
                    'result' => $ret,
                ];
            }
        } catch (\BadMethodCallException $e) {
            return [
                'id' => null,
                'jsonrpc' => "2.0",
                'error' => [
                    'code' => -32601,
                    'message' => 'Method call error'
                ]
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'id' => null,
                'jsonrpc' => "2.0",
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid params'
                ],
                'data' => [
                    'type' => 'TypeError',
                    'message' => $e->getMessage()
                ]
            ];
        } catch (\Exception $e) {
            foreach ($this->handleExceptions as $exceptionClass) {
                if (get_class($e) === $exceptionClass) {
                    return [
                        'jsonrpc' => "2.0",
                        'error' => [
                            'code' => -32000,
                            'message' => 'Server Error'
                        ]
                    ];
                }
            }
            throw $e;
        }
    }

    public function handle($requestJson = null)
    {
        $requestJson = $requestJson ? $requestJson : file_get_contents('php://input');
        $request = json_decode($requestJson, true);
        if (!is_array($request)) {
            return [
                'id' => null,
                'jsonrpc' => "2.0",
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error'
                ],
            ];
        }
        if (isset($request['method'])) {
            return $this->processMethod($request);
        } else {
            // batch call
            $result = [];
            foreach ($request as $req) {
                $request[] = $this->processMethod($req);
            }
            return $result;
        }
    }
}