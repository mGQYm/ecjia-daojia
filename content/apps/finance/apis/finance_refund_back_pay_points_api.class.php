<?php
//
//    ______         ______           __         __         ______
//   /\  ___\       /\  ___\         /\_\       /\_\       /\  __ \
//   \/\  __\       \/\ \____        \/\_\      \/\_\      \/\ \_\ \
//    \/\_____\      \/\_____\     /\_\/\_\      \/\_\      \/\_\ \_\
//     \/_____/       \/_____/     \/__\/_/       \/_/       \/_/ /_/
//
//   上海商创网络科技有限公司
//
//  ---------------------------------------------------------------------------------
//
//   一、协议的许可和权利
//
//    1. 您可以在完全遵守本协议的基础上，将本软件应用于商业用途；
//    2. 您可以在协议规定的约束和限制范围内修改本产品源代码或界面风格以适应您的要求；
//    3. 您拥有使用本产品中的全部内容资料、商品信息及其他信息的所有权，并独立承担与其内容相关的
//       法律义务；
//    4. 获得商业授权之后，您可以将本软件应用于商业用途，自授权时刻起，在技术支持期限内拥有通过
//       指定的方式获得指定范围内的技术支持服务；
//
//   二、协议的约束和限制
//
//    1. 未获商业授权之前，禁止将本软件用于商业用途（包括但不限于企业法人经营的产品、经营性产品
//       以及以盈利为目的或实现盈利产品）；
//    2. 未获商业授权之前，禁止在本产品的整体或在任何部分基础上发展任何派生版本、修改版本或第三
//       方版本用于重新开发；
//    3. 如果您未能遵守本协议的条款，您的授权将被终止，所被许可的权利将被收回并承担相应法律责任；
//
//   三、有限担保和免责声明
//
//    1. 本软件及所附带的文件是作为不提供任何明确的或隐含的赔偿或担保的形式提供的；
//    2. 用户出于自愿而使用本软件，您必须了解使用本软件的风险，在尚未获得商业授权之前，我们不承
//       诺提供任何形式的技术支持、使用担保，也不承担任何因使用本软件而产生问题的相关责任；
//    3. 上海商创网络科技有限公司不对使用本产品构建的商城中的内容信息承担责任，但在不侵犯用户隐
//       私信息的前提下，保留以任何方式获取用户信息及商品信息的权利；
//
//   有关本产品最终用户授权协议、商业授权与技术服务的详细内容，均由上海商创网络科技有限公司独家
//   提供。上海商创网络科技有限公司拥有在不事先通知的情况下，修改授权协议的权力，修改后的协议对
//   改变之日起的新授权用户生效。电子文本形式的授权协议如同双方书面签署的协议一样，具有完全的和
//   等同的法律效力。您一旦开始修改、安装或使用本产品，即被视为完全理解并接受本协议的各项条款，
//   在享有上述条款授予的权力的同时，受到相关的约束和限制。协议许可范围以外的行为，将直接违反本
//   授权协议并构成侵权，我们有权随时终止授权，责令停止损害，并保留追究相关责任的权力。
//
//  ---------------------------------------------------------------------------------
//
defined('IN_ECJIA') or exit('No permission resources.');

/**
 * 订单退款退还消费积分,及扣除下单赠送积分接口
 * @author 
 */
class finance_refund_back_pay_points_api extends Component_Event_Api {

    /**
     * @param integer refund_id       必填，退款申请id
     * @return ecjia_error|true
     */
    public function call(& $options)
    {
        if (!array_get($options, 'refund_id')) {
            return new ecjia_error('invalid_parameter', '请求接口refund_back_pay_points_api参数无效');
        }
        
        $refund_id 			= array_get($options, 'refund_id');
        $refund_info 		= RC_DB::table('refund_order')->where('refund_id', $refund_id)->first();
        
        $integral_name = ecjia::config('integral_name');
        if (empty($integral_name)) {
        	$integral_name = '积分';
        }
        
        if ($refund_info['user_id'] > 0) {
        	if ($refund_info['integral'] > 0) { //下单有没使用积分
        		//是否已退过积分
        		$refund_back_integral_info = RC_DB::table('account_log')->where('user_id', $refund_info['user_id'])->where('from_type', 'refund_back_integral')->where('from_value', $refund_info['order_sn'])->first();
        		if (empty($refund_back_integral_info)) {
        			//退还下单使用的积分
        			$options = array(
        					'user_id' 		=> $refund_info['user_id'],
        					'point' 	    => intval($refund_info['integral']),
        					'change_desc' 	=> '订单退款，退还订单' . $refund_info['order_sn'] . '下单时使用的'.$integral_name,
        					'change_type' 	=> ACT_REFUND,
        					'from_type' 	=> 'refund_back_integral',
        					'from_value' 	=> $refund_info['order_sn']
        			);
        			$res = RC_Api::api('finance', 'pay_points_change', $options);
        			if (is_ecjia_error($res)) {
        				return $res;
        			}
        		}
        	}
        	/*所退款订单，有没赠送积分；有赠送的话，赠送的积分扣除*/
        	$order_give_integral_info = RC_DB::table('account_log')->where('user_id', $refund_info['user_id'])->where('from_type', 'order_give_integral')->where('from_value', $refund_info['order_sn'])->first();
        	if (!empty($order_give_integral_info)) {
        		//是否已扣除过积分
        		$refund_deduct_integral_info = RC_DB::table('account_log')->where('user_id', $refund_info['user_id'])->where('from_type', 'refund_deduct_integral')->where('from_value', $refund_info['order_sn'])->first();
        		if (empty($refund_deduct_integral_info)) {
        			$options = array(
        					'user_id'       => $refund_info['user_id'],
        					'point'         => intval($order_give_integral_info['pay_points']) * (-1),
        					'change_desc'   => '订单退款，扣除订单' . $refund_info['order_sn'] . '下单时赠送的'.$integral_name,
        					'change_type'   => ACT_REFUND,
        					'from_type'     => 'refund_deduct_integral',
        					'from_value'    => $refund_info['order_sn']
        			);
        			
        			$res = RC_Api::api('finance', 'pay_points_change', $options);
        			if (is_ecjia_error($res)) {
        				return $res;
        			}
                }
        	}
        }
        return true;
    }
    
    
    /**
     * 记录帐户变动
     *
     * @param int $user_id 用户id
     * @param int $point 消费积分变动
     * @param string $change_desc 变动说明
     * @param int $change_type 变动类型：参见常量文件
     * @return void
     */
    private function log_account_change($user_id, $point = 0, $change_desc = '', $change_type = ACT_OTHER, $from_type = '', $from_value = '')
    {
        /* 插入帐户变动记录 */
        $account_log = array (
            'user_id'			=> $user_id,
            'user_money'		=> 0,
            'frozen_money'		=> 0,
            'rank_points'		=> 0,
            'pay_points'		=> $point,
            'change_time'		=> RC_Time::gmtime(),
            'change_desc'		=> $change_desc,
            'change_type'		=> $change_type,
            'from_type'			=> empty($from_type) ? '' : $from_type,
            'from_value'		=> empty($from_value) ? '' : $from_value
        );

        return RC_DB::transaction(function () use ($account_log, $user_id) {

            $log_id = RC_DB::table('account_log')->insertGetId($account_log);

            /* 更新用户信息 */
            RC_DB::table('users')->where('user_id', $user_id)->increment('pay_points', intval($account_log['pay_points']));

            return $log_id;
        });
    }
    
}

// end