```
class MyEvents extends \Neko\Events\Events {
    const EventCallBack = "EventCallBack";

    public static function EventCallBack(\Closure $func)
    {
        self::listen(self::EventCallBack,$func);
    }
}

MyEvents::EventCallBack(function (){
    var_dump('trigger'.random_int(1,10));
    return random_int(1,10);
});

MyEvents::trigger(MyEvents::EventCallBack);
//string(9) "trigger10"

MyEvents::trigger(MyEvents::EventCallBack);
//string(8) "trigger2"

// 会缓存结果
$num1 = MyEvents::trigger_once(MyEvents::EventCallBack);
$num2 = MyEvents::trigger_once(MyEvents::EventCallBack);
//array(2) {
//  ["num1"]=>
//  int(9)
//  ["num2"]=>
//  int(9)
//}

// 清除缓存结果
MyEvents::clear_cache(MyEvents::EventCallBack);
$num1 = MyEvents::trigger_once(MyEvents::EventCallBack);
$num2 = MyEvents::trigger_once(MyEvents::EventCallBack);
var_dump(['num1'=>$num1,'num2'=>$num2]);
```