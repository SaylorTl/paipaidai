<?php
/**
 * Created by PhpStorm.
 * User: hodor-out
 * Date: 2018/3/5
 * Time: 15:32
 */

/**陪标策略

INFO 20171020 14:20:35 26.94[credit:0,loans:67015,req:118399,600time:269734]{AA=1.09744596E8, AA/12.0/24=1003188.0, AA/9
.0/1=15000.0, A=1.0865851E7, AA/12.0/12=67100.0, B=3.4738983E7, C=7.869157E7, AA/9.5=7.1463852E7, D=460974.0, AA/11.0/15
=505693.0, AA/11.0/18=1717272.0, AA/10.0/9=1079238.0, AA/11.0/12=3.1938822E7, AA/9.0=15000.0, AA/9.5/3=7.1463852E7, AA/1
2.0/18=1168400.0, AA/11.0=3.4161787E7, AA/10.0/3=113300.0, AA/10.0=1541269.0, AA/10.0/6=348731.0, AA/12.0=2562688.0, AA/
12.0/36=324000.0}
2017年10月20日
12% 标中12月24月、36月
11%标 12、15、18月
10%  3、6.9个月
9.5% 3个月
9%
 *
 * */
$config['PeiBidFlag']  = true;	//是否投陪标
$config['CreditBidFlag'] = false;	//是否投信用标
$config['NoWasteCountFlag'] = true;	//true时表示不能允许有流标、废标、撤标等


    //正常小额投标时的策略
    //2017年9月30日，假期模式
$config['Pei12Month'] = 36;	//12%的陪标的月份，缺省9月标12%，偶尔会12个月的12%标
$config['Pei11Month'] = 18;	//是否投11%的3月标,小于等于该月数的标才投
$config['Pei10Month'] = 0;	//是否投10%的1月标,小于等于该月数的标才投
$config['PeiRateLimit'] = 10; //12.5f;	//陪标的最低投标利率

    //2017年9月29日之前，12.5%的18月标比较常见，正常工作日
//	static int pei12Month = 9;	//12%的陪标的月份，缺省9月标12%，偶尔会12个月的12%标
//	static boolean pei11Flag = false;	//是否投11%的3月标
//	static boolean pei10Flag = false;	//是否投10%的1月标
//	static double peiRateLimit = 12.5f; //12.5f;	//陪标的最低投标利率



    //大额时的策略
//	static int pei12Month = 12;	//12%的陪标的月份，缺省9月标12%，偶尔会12个月的12%标
//	static boolean pei11Flag = true;	//是否投11%的3月标
//	static boolean pei10Flag = true;	//是否投10%的1月标
//	static double peiRateLimit = 12.0f; //12.5f;	//陪标的最低投标利率

$config['CreditLevel']=true;	//投标是否限制严格

$config['PeiBidAmount'] = 500;	//除13以上陪标的单笔投资金额
$config['CreditBidAmount'] = 50;	//信用标投标金额

    //设置为100时，实际能跑到40秒100次， 设置为50时3分40秒秒能跑600次
    //设置为30时2分50秒能跑600次
$config['SleepTime'] = 2;	//两次查询之间的等待时间，每秒最多10次访问,600次每分钟（原来1分钟1000次），（100-200）



    //不严格的情况下,35次允许逾期1次，基数45，
    //严格的情况下，45次允许逾期1次，基数60
$config['OverduelessNormalCountBase'] = 60;	//允许逾期必须还款次数大于一定数额
$config['OverduelessNormalCountPerOne'] = 45;	//每多少次允许逾期1次

    //单笔金额不能太大(1080个标中<5000:1016个；<4000:953个；<3500:902个；<3000:855个，<2000:681个；<1000:314个
$config['AmountLimit'] = 8000;	//曾经设置为11000, 8000，5000

    //待还金额不能太大
$config['OwingAmountLimit'] = 7000; //曾经设置为10000， 9000

    //最大金额
$config['MaxOwingLimit6']  =14000;	//6个月的最大允许的待还金额，最大测试过14000
$config['MaxOwingLimit12'] =10000;	//12个月最大允许的待还金额

