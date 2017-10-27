<?php

class Validation {

	public static function ic($data) {
		$regexDate  = '(((([02468][048])|([13579][26]))((((0[13578])|(1[02]))((0[1-9])|([12][0-9])|(3[01])))|(((0[469])|11)((0[1-9])|([12][0-9])|30))|(02((0[1-9])|([12][0-9])))))|((([02468][1235679])|([13579][01345789]))((((0[13578])|(1[02]))((0[1-9])|([12][0-9])|(3[01])))|(((0[469])|11)((0[1-9])|([12][0-9])|30))|(02((0[1-9])|(1[0-9])|(2[0-8]))))))'; //YYMMDD
		$regexDash  = '[-]?'; //-
		$regexState = '[0-9]{2}'; //SS
		$regexEnd   = '[0-9]{3}[0-9]'; //RRRR

		$regexWODash              = '%^('.$regexDate.$regexState.$regexEnd.')$%';
		$regexwithDash            = '%^('.$regexDate.$regexDash.$regexState.$regexDash.$regexEnd.')$%';
		$regexICWDashIncomplete   = '%^('.$regexDate.$regexDash.$regexState.$regexDash.'[0-9]{1,3})$%';
		$regexOldICWOSpace        = '%^((A|a|H|h|K|k)?[0-9]{7})$%';
		$regexOldICWSpace         = '%^((A|a|H|h|K|k)?[\s-]+[0-9]{7})$%'; // https://www.consolut.com/en/s/sap-ides-access/d/s/doc/YV-5L205/
		$regexRF                  = '%^((R|r)(\/)?(f|F)[\s\/\-]*[0-9]{5,6})$%'; // malaysia armed force
		$regexCompNo1             = '%^([0-9]{4,7}[\s-]*[a-zA-Z]{1})$%';
		$regexCompNo2             = '%^((00|([a-zA-Z]{2}))[0-9]{4,7}[\s-]*[a-zA-Z]{1})$%'; // https://forum.lowyat.net/topic/1734602/all

		$regexTotalInvalid        = '%^([0a-zA-Z\s-_*.]+)$%';

		if(empty($data)) {
			return 'blank';
		}
		elseif(preg_match($regexTotalInvalid, $data)) {
			return 'total_invalid';
		}
		elseif(preg_match($regexWODash, $data)) { //860502012351
			return 'complete';
		}
		elseif(preg_match($regexwithDash, $data)) { //860201-03-5412
			return 'complete_wo_format';
		}
		elseif(preg_match($regexICWDashIncomplete, $data)) { // 860201-25-12
			return 'incomplete_ic_w_dash';
		}
		elseif(preg_match($regexOldICWOSpace, $data)) { // A1234567, 1234567
			return 'old_ic_wo_space';
		}
		elseif(preg_match($regexOldICWSpace, $data)) { // H 1234567 H-1234567
			return 'old_ic_w_space';
		}
		elseif(preg_match($regexRF, $data)) { // RF123456
			return 'rf_no';
		}
		elseif(preg_match($regexCompNo1, $data)) { // 1234567 X, 1234567-X (company)
			return 'company_no_1';
		}
		elseif(preg_match($regexCompNo2, $data)) { //001234567-X, PP1234567-S (enterprise)
			return 'company_no_2';
		}
		else {
			return 'invalid';
		}
	}

	public static function email($data) {
		$regex = '/^[\p{L}0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[\p{L}0-9!#$%&\'*+\/=?^_`{|}~-]+)*@' . '(?:[_\p{L}0-9][-_\p{L}0-9]*\.)*(?:[\p{L}0-9][-\p{L}0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,})' . '$/ui';

		$regexTotalInvalid = '%^([0-9\/\\\s_*.-]+)$%';

		if(empty($data)) {
			return 'blank';
		}
		elseif(preg_match($regexTotalInvalid, $data)) {
			return 'total_invalid';
		}
		elseif(preg_match($regex, $data)) {
			return 'complete';
		}
		else {
			return 'invalid';
		}
	}

	public static function phoneNo($data) {
		$regexInt   = '\+?(6)?';
		$regexMobileTelco = '(010|011|012|013|014|015|016|017|018|019)';
		$regexFixTelco = '(02|03|04|05|06|07|080|081|082|083|084|085|086|087|088|089|09)';
		$regexNo    = '[1-9][0-9]{6,8}';
		$regexNoWSpace = '[1-9][0-9]{2}[\s]*[0-9]{4,5}';

		$regexMobile         = '%^('.$regexInt.$regexMobileTelco.$regexNo.')$%';
		$regexFixLine        = '%^('.$regexInt.$regexFixTelco.$regexNo.')$%';
		$regexMobileWDash    = '%^('.$regexInt.$regexMobileTelco.'[--\s]+'.'(('.$regexNo.')|('.$regexNoWSpace.'))'.')$%';
		$regexFixLineWDash   = '%^('.$regexInt.$regexFixTelco.'[--\s]+'.'(('.$regexNo.')|('.$regexNoWSpace.'))'.')$%';

		$regexTotalInvalid = '%^([0a-zA-Z\s-_*.]+)$%';

		if(empty($data)) {
			return 'blank';
		}
		elseif(preg_match($regexTotalInvalid, $data)) {
			return 'total_invalid';
		}
		elseif(preg_match($regexMobile, $data)) { // 01723123546
			return 'mobile_no';
		}
		elseif(preg_match($regexMobileWDash, $data)) { // 012-78451252
			return 'mobile_no_w_dash';
		}
		elseif(preg_match($regexFixLine, $data)) { // 0356412563
			return 'fix_line_no';
		}
		elseif(preg_match($regexFixLineWDash, $data)) { // 03-45212524
			return 'fix_line_no_w_dash';
		}
		else {
			return 'invalid';
		}
	}

	public static function name($data) {
		$regexWOFormat = "%^([a-zA-Z\s'@.\/`\(\)&-,]+)$%";
		$regexValid    = "%^([a-zA-Z\(\)]{1}[a-zA-Z\s'@.\/`\(\)&-,]+[a-zA-Z\(\).]{1})$%";

		$regexInvalid = array(
				'[0-9\/\\\s_*.-]+', // numeric & symbol only
				'[Nn]{1}[.\s]*[Ii]{1}[.\s]*[Ll]{1}[.\s]*', // nil
				'[Nn]{1}[.\s]*[Oo]{1}[.\s]*[Nn]{1}[.\s]*[Ee]{1}[.\s]*', // none
				'[Tt]{1}[.\s]*[Ii]{1}[.\s]*[Aa]{1}[.\s]*[Dd]{1}[.\s]*[Aa]{1}[.\s]*', // tiada
				'[Tt]{1}[Ii]{1}[Dd]{1}[Aa]{1}[Kk]{1}[.\s]*[Ll]{1}[Ee]{1}[Nn]{1}[Gg]{1}[Kk]{1}[Aa]{1}[Pp]{1}', // tidak lengkap
			);

		$regexTotalInvalid = '%^(('.implode(')|(', $regexInvalid).'))$%';

		if(empty($data)) {
			return 'blank';
		}
		elseif(preg_match($regexTotalInvalid, $data)) {
			return 'total_invalid';
		}
		elseif(preg_match($regexValid, $data)) {
			return 'complete';
		}
		elseif(preg_match($regexWOFormat, $data)) {
			return 'complete_wo_format';
		}
		else {
			return 'invalid';
		}
	}
}