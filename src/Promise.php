<?php
namespace Neko\Events;
class Promise
{
    private $value;
    private $reason;
    private $state;
    private $fulfilledCallbacks = [];
    private $rejectedCallbacks = [];

    public function __construct(\Closure $func = null)
    {
        $this->state = PromiseState::PENDING;

        $func([$this, 'resolve'], [$this, 'reject']);
    }

    public function then(\Closure $onFulfilled = null, \Closure $onRejected = null)
    {
        // 此处作用是兼容then方法的以下四种参数变化，catchError就是第二种情况
        // 1. then($onFulfilled, null)
        // 2. then(null, $onRejected)
        // 3. then(null, null)
        // 4. then($onFulfilled, $onRejected)
        $onFulfilled = is_callable($onFulfilled) ? $onFulfilled :  function ($value) {return $value;};
        $onRejected = is_callable($onRejected) ? $onRejected :  function ($reason) {throw $reason;};

        $thenPromise = new Promise(function ($reslove, $reject) use (&$thenPromise, $onFulfilled, $onRejected) {

            //$this 代表的当前的Promise对象，不要混淆了

            // 如果是异步回调，状态未变化之前，then的回调方法压入相应的数组方便后续调用
            if ($this->state == PromiseState::PENDING) {
                $this->fulfilledCallbacks[] = static function() use ($thenPromise, $onFulfilled, $reslove, $reject){
                    try {
                        $value = $onFulfilled($this->value);
                        $this->resolvePromise($thenPromise, $value, $reslove, $reject);
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                };

                $this->rejectedCallbacks[] = static function() use ($thenPromise, $onRejected, $reslove, $reject){
                    try {
                        $reason = $onRejected($this->reason);
                        $this->resolvePromise($thenPromise, $reason, $reslove, $reject);
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                };
            }

            // 如果状态是fulfilled，直接回调执行并传参value
            if ($this->state == PromiseState::FULFILLED) {
                try {
                    $value = $onFulfilled($this->value);
                    $this->resolvePromise($thenPromise, $value, $reslove, $reject);
                } catch (\Exception $e) {
                    $reject($e);
                }
            }

            // 如果状态是rejected，直接回调执行并传参reason
            if ($this->state == PromiseState::REJECTED) {
                try {
                    $reason = $onRejected($this->reason);
                    $this->resolvePromise($thenPromise, $reason, $reslove, $reject);
                } catch (\Exception $e) {
                    $reject($e);
                }
            }

        });

        // 返回对象自身，实现链式调用
        return $thenPromise;

    }

    public function catchError($onRejected)
    {
        return $this->then(null, $onRejected);
    }

    /**
     * 解决Pormise链式then传递
     * 可参考 [Promises/A+]2.3 [https://promisesaplus.com/#the-promise-resolution-procedure]
     * @param $thenPromise
     * @param $x            $x为thenable对象
     * @param $resolve
     * @param $reject
     */
    private function resolvePromise($thenPromise, $x, $resolve, $reject)
    {
        $called = false;

        if ($thenPromise === $x) {
            return $reject(new \Exception('循环引用'));
        }

        if ( is_object($x) && method_exists($x, 'then')) {
            try {
                $resolveCb = function ($value) use ($thenPromise, $resolve, $reject, $called) {
                    if ($called) return;
                    $called = true;
                    // 成功值y有可能还是promise或者是具有then方法等，再次resolvePromise，直到成功值为基本类型或者非thenable
                    $this->resolvePromise($thenPromise, $value, $resolve, $reject);
                };

                $rejectCb = function ($reason) use ($thenPromise, $resolve, $reject, $called) {
                    if ($called) return;
                    $called = true;
                    $reject($reason);
                };

                call_user_func_array([$x, 'then'], [$resolveCb, $rejectCb]);
            } catch (\Exception $e) {
                if ($called) return ;
                $called = true;
                $reject($e);
            }

        } else {
            if ($called) return ;
            $called = true;
            $resolve($x);
        }
    }

    /**
     * 执行回调方法里的resolve绑定的方法
     * 本状态只能从pending->fulfilled
     * @param null $value
     */
    public function resolve($value = null)
    {
        if ($this->state == PromiseState::PENDING) {
            $this->state = PromiseState::FULFILLED;
            $this->value = $value;

            array_walk($this->fulfilledCallbacks, function ($callback) {
                $callback(); //因为回调本身携带了作用于，所以直接调用，无法参数
            });
        }
    }

    /**
     * 执行回调方法里的rejected绑定的方法
     * 本状态只能从pending->rejected
     * @param null $reason
     */
    public function reject($reason = null)
    {
        if ($this->state == PromiseState::PENDING) {
            $this->state = PromiseState::REJECTED;
            $this->reason = $reason;

            array_walk($this->rejectedCallbacks, function ($callback) {
                $callback(); //因为回调本身携带了作用于，所以直接调用，无法参数
            });
        }
    }

    public function getState()
    {
        return $this->state;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getReason()
    {
        return $this->reason;
    }
}