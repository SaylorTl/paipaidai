<?php
/**
 * Created by PhpStorm.
 * User: hodor-out
 * Date: 2018/3/5
 * Time: 15:43
 */


/**flag为true表示是否计算其他人投标的影响*/
function  getCreditLimit($loaninfo){
    //至少要认证身份证和电话
    //进行身份证认证(最简单的情况)就可以借款，可以不验证手机（但大部分要验证手机）
    if(loan.PhoneValidate==0){	//99%以上的都有手机验证
        loan.flag="XPhone";
        return 0;
    }

    /*
     OverdueMoreCount | count(*) |
-----------------+----------+
           0 |   227998 |
           1 |      895 |
     */
    //有超期还款记录的
    if(loan.OverdueMoreCount>0){
        loan.flag="XOverdueM";
        return 0;
    }

    /*
     +------------------+----------+
| OverduelessCount | count(*) |
+------------------+----------+
|                0 |   161414 |
|                1 |    28679 |
|                2 |    12464 |
|                3 |     7035 |
|                4 |     4506 |
     */

    //超过3次的直接过掉，后面有更严格的要求
    if(loan.OverdueLessCount>=3){
        loan.flag="XOverdueL";
        return 0;
    }



    //学历的情况
//		if(loan.CertificateValidate==0) continue;	//学历认证的占比1/3

    double owing = loan.amount + loan.OwingAmount;	//如果借款成功后的待还


		boolean strictflag=false;	//对与很好的标
		if(loan.Months==6 && "D".equalsIgnoreCase(loan.creditCode)){
            if(loan.NormalCount>45 && owing<5500) strictflag=true;
            else if(loan.NormalCount>=70 && owing<6500) strictflag=true;
            else if(loan.NormalCount>=100 && owing<7500) strictflag=true;
        }

		//不允许逾期(在openAPI自动投资中好像特别关注)
		if(loan.OverdueLessCount>=1){
            boolean overdueflag = true;
			//不严格的情况下,35倍，基数45，
			//严格的情况下，45倍，基数60
			if(loan.NormalCount>PPDPolicy.overduelessNormalCountBase
                && (loan.NormalCount> (loan.OverdueLessCount*PPDPolicy.overduelessNormalCountPerOne))){
                //overdueflag = false;
            }
			if(overdueflag){
                //对于逾期一次的
                loan.flag="OverdueL";
                return 0;
            }
		}

		if(PPDPolicy.noWasteCountFlag){
            //不允许有流标和撤标的情况
            if(loan.FailedCount>0 || (!strictflag && loan.FailedCount==1)){
                loan.flag="XFailC";
                return 0;	//失败
            }
            if(loan.CancelCount>0 || (!strictflag && loan.CancelCount==1)){
                loan.flag="XCancelC";
                return 0;	//撤销
            }
            if(loan.WasteCount>0 || (!strictflag && loan.WasteCount==1)){
                loan.flag="XWastC";
                return 0;		//流标
            }
        }


		if("B".equals(loan.creditCode)){
            //好像大家都不喜欢B类
            loan.flag="XB";
            return 0;
        }

		if("E".equals(loan.creditCode) || "F".equals(loan.creditCode)){
            loan.flag="XEF";
            return 0;
        }


		if(!"C".equals(loan.creditCode) && !"D".equals(loan.creditCode)){
            //由于金额小只投资C和D类
            loan.flag="X!CD";
            return 0;
        }

		//系统禁止18岁以下的人借款，同时34岁及以下占比80%以上，40岁及以下92%，38岁及以下占比90%
		//48岁及以下占比98%
		//系统中年龄分布和性别的分布好像关系不大
		//***根据自己的黑名单统计30岁以上借款小额的问题比较大(原来是不能大于32岁）
		if(loan.age<20 || loan.age>=30){
            loan.flag="XAge";
            return 0;
        }

		//在有NormalCount限制在25以上时，其实该过滤条件没有用
		if(loan.successCount<3){
            loan.flag="XSuccessC";
            return 0;
        }

		//以前设置成25，待还6000
		if(loan.NormalCount>25 && owing<=5000 && loan.Months==6 && "D".equalsIgnoreCase(loan.creditCode)){
            //还款记录较少的小额贷款
            loan.flag="Little";
            logger.info("!!!["+loan.flag+"]" + loan);
        }
        else if(loan.NormalCount<35){
            loan.flag="XNormalC";
            return 0;	//20的情况下出现的标很多
        }


		//成功还款次数/借款次数， 如果大于一定值，则表示前面的还款比较正常
		//该参数非常重要，用于判断通过全额的提前还款进行刷信用的情况
		//如果进行全额本息的提前还款并不会导致异常
		double r = (double)(loan.NormalCount)/loan.successCount;
		if(r<3){
            loan.flag="XQiza";
            return 0;
        }

		//计算可能的
		int owinglimit = 6000;

		if (loan.gender == 2) owinglimit += 1000; // 女

		if (loan.CertificateValidate == 1 && loan.studyStyle != null
            && loan.studyStyle.length() > 0) {
            // CertificateValidate学位认证, （EducateValidate学籍认证）
            if ("普通".equals(loan.studyStyle) || "普通全日制".equals(loan.studyStyle)) {
                if (loan.educationDegree != null
                    && loan.educationDegree.indexOf("本科") >= 0) {
                    owinglimit += 3000;
                } else {
                    owinglimit += 2000;
                }
            } else if ("研究生".equals(loan.studyStyle)) {
                owinglimit += 4000;
            } else
                owinglimit += 1000;
        }
		if (loan.VideoValidate == 1 || loan.NciicIdentityCheck == 1) {
            // 视频认证或者户籍认证
            owinglimit += 1000;
        }
		if (loan.CreditValidate == 1) {
            owinglimit += 2000; // 人行信用认证
        }

		//依据已经还款的次数进行信用评价
		if (loan.NormalCount >= 25) {
            int rebitlimit = 200 * (loan.NormalCount - 20);
			owinglimit += rebitlimit;
		}


		//依据累计还款的额度信用评价
		if (loan.TotalPrincipal >= 0) {
            int rebitlimit = (int)((loan.TotalPrincipal - loan.OwingPrincipal)/5);
			if(rebitlimit>0) owinglimit += rebitlimit;
		}

		// 如果有人快速投标，加大上限
		//从起始投标到现在开始的时间
		if(flag){
            int loanMinute = parseBidEndTime(loan.DeadLineTimeOrRemindTimeStr);
			if (loanMinute >= 0 && loanMinute <= 10 && loan.lenderCount > 0) {
                owinglimit += loan.lenderCount * 1000;
            }
		}


		// 不能太高, 目前6个月的额度比12个月的额度高
		if (owinglimit > PPDPolicy.maxOwingLimit6)
            owinglimit = PPDPolicy.maxOwingLimit6;
		if (loan.Months >= 12) {
            if (owinglimit > PPDPolicy.maxOwingLimit12)
                owinglimit = PPDPolicy.maxOwingLimit12;
        }

		return owinglimit;
	}