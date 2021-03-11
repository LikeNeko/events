<?php

namespace Neko\Events;
class Events
{
    // 事件列表
    protected static  $listens = [];

    // key是否唯一为true全局key只能存在一个，需要监听多个相同的key则需要先remove
    public static  $key_once = false;
    // 是否用return 返回
    public static  $is_return = true;


    /**
     * @return array
     */
    public static function getListens(): array
    {
        return self::$listens;
    }

    /**
     * 添加一个事件监听
     *
     * @param       $event
     * @param       $callback
     * @param false $once
     *
     * @return bool
     */
    public static function listen($event, $callback, $once = false): bool
    {
        if (!is_callable($callback)) {
            return false;
        };
        // 如果key已经设置过了则反false
        if (self::$key_once) {
            if (isset(self::$listens[$event])) {
                return false;
            }
        }
        $info = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
        $func = 'no_func';
        if (isset($info[1])) {
            $to_info  = $info[1];
            $function = $to_info['function'];
            $func     = "{$function}";
        }
        $call_line               = $info[0]['line'];
        self::$listens[$event][] = ['callback' => $callback, 'once' => $once, 'ext_data' => [
            'function' => $func,
            'line'     => $call_line
        ]];
        return true;
    }

    /**
     * 添加一个只会触发一次的事件监听
     *
     * @param $event
     * @param $callback
     *
     * @return bool
     */
    public static function one($event, $callback): bool
    {
        return self::listen($event, $callback, true);
    }

    /**
     * 移除事件监听
     *
     * @param      $event
     * @param null $index
     *
     * @return void
     */
    public static function remove($event, $index = null)
    {
        if (is_null($index))
            unset(self::$listens[$event]);
        else
            unset(self::$listens[$event][$index]);
    }

    /**
     * 执行闭包
     *
     * @param       $event
     * @param       $args
     * @param false $is_cache
     *
     * @return array
     */
    private static function call($event, $args, $is_cache = false)
    {
        $return = [];
        foreach ((array)self::$listens[$event] as $index => $listen) {
            $callback = $listen['callback'];
            $listen['once'] && self::remove($event, $index);

            // 缓存上一次执行的结果
            if (array_key_exists('result_cache', $listen) && $is_cache) {
                $return[] = $listen['result_cache'];
            } else {
                $r = call_user_func_array($callback, $args);

                self::$listens[$event][$index]['result_cache'] = $r;
                $return[]                                      = $r;
            }
        }
        return $return;
    }

    /**
     * 触发一个事件
     *
     * @param mixed ...$event_names
     *
     * @return mixed|Promise
     */
    public static function trigger(...$event_names)
    {
        if (!func_num_args()) {
            return null;
        }
        $args = func_get_args();
        // 去掉方法名
        $event = array_shift($args);
        if (!isset(self::$listens[$event])) {
            return null;
        }
        if (self::$is_return) {
            $return = self::call($event, $args);
            return count($return) > 1 ? $return : $return[0];
        } else {
            $promise = new Promise(function ($resolve, $reject) use ($event, $args) {
                $return = self::call($event, $args);
                if (count($return) > 1) {
                    $resolve($return);
                } else {
                    $resolve($return[0]);
                }
            });
            return $promise;
        }

    }

    /**
     * 触发一个事件
     *
     * @param mixed ...$event_names
     *
     * @return mixed|Promise
     */
    public static function trigger_once(...$event_names)
    {
        if (!func_num_args()) {
            return null;
        }
        $args = func_get_args();
        // 去掉方法名
        $event = array_shift($args);
        if (!isset(self::$listens[$event])) {
            return null;
        }

        if (self::$is_return) {
            $return = self::call($event, $args, true);
            return count($return) > 1 ? $return : $return[0];
        } else {
            $promise = new Promise(function ($resolve, $reject) use ($event, $args) {
                $return = self::call($event, $args, true);
                if (count($return) > 1) {
                    $resolve($return);
                } else {
                    $resolve($return[0]);
                }
            });
            return $promise;
        }
    }

    /**
     * 清理事件的缓存结果
     *
     * @param      $event
     * @param null $index
     *
     * @return void
     */
    public static function clear_cache($event, $index = null)
    {
        if (is_null($index))
            foreach (self::$listens[$event] as $key => $item) {
                unset(self::$listens[$event][$key]['result_cache']);
            }
        else
            unset(self::$listens[$event][$index]['result_cache']);
    }
}