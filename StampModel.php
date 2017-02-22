<?php
/**
 * @category	Model
 * @package	Stamp
 * @author		matsu
 * @copyright	Copyright (c) matsu All Rights Reserved.
 * @powered	Noir
 */


require_once "libraries/CommonModel.php";
require_once "libraries/DbGameGift.php";
require_once "libraries/DbTitle.php";
require_once "libraries/DbUser.php";
require_once "libraries/DbVpreca.php";
require_once "libraries/DbCommon.php";
require_once "libraries/CSRF.php";


class StampModel extends CommonModel {


	/** ==================================================
	 * Define
	 * ================================================= */


	const EXCHANGE_STAMP_POINT = 500;

// 変更てすと２


	/** ==================================================
	 * Property
	 * ================================================= */





	/** ==================================================
	 * Constructor / Destructor
	 * ================================================= */


	/**
	 * コンストラクタ
	 */
	public function __construct() {
		parent::__construct();
	}





	/** ==================================================
	 * Public Method
	 * ================================================= */


	/**
	 * index
	 */
	public function indexModel() {
		try {
			if (!$this->isLogin()) {
				return "/login";
			}
			
			$dbUser = new DbUser($this->_config);
			$profile = $this->getProfile();
			// スタンプ取得
			$stamps = $dbUser->getStamp(array("user_id" => $profile["user_id"]));
			$this->_view->assign("stamps", $stamps);

			if ($profile["user_id"] == 1026884 || $profile["user_id"] == 1820095) {
				throw new Exception($this->_getErrorMsg("stamp", "injustice"));
			}


			// スタンプ合計計算
			$total_stamp_point = 0;
			$total_stamp_per = 0;
			foreach ($stamps as $stamp) {
				$total_stamp_point += $stamp["stamp_point"];
			}
			$total_stamp_per = floor($total_stamp_point / self::EXCHANGE_STAMP_POINT * 100);
			if ($total_stamp_per > 100) {
				$total_stamp_per = 100;
			}
			$this->_view->assign("total_stamp_point", $total_stamp_point);
			$this->_view->assign("total_stamp_per", $total_stamp_per);
		} catch (Exception $e) {
			$this->_view->assign("error", $e->getMessage());
			return false;
		}
		return true;
	}


	/**
	 * exchange
	 */
	public function exchangeModel() {
		try {
			if (!$this->isLogin()) {
				return "/login";
			}
			
			$dbUser = new DbUser($this->_config);
			$profile = $this->getProfile();
			if ($profile["user_id"] == 1026884 || $profile["user_id"] == 1820095) {
				throw new Exception($this->_getErrorMsg("stamp", "injustice"));
			}

			$total_stamp_point = 0;
			$total_stamp_per = 0;

			// スタンプ合計計算
			$stamps = $dbUser->getStamp(array("user_id" => $profile["user_id"]));
			foreach ($stamps as $stamp) {
				$total_stamp_point += $stamp["stamp_point"];
			}
			$total_stamp_per = floor($total_stamp_point / self::EXCHANGE_STAMP_POINT * 100);
			if ($total_stamp_per > 100) {
				$total_stamp_per = 100;
			}
			$this->_view->assign("total_stamp_point", $total_stamp_point);
			$this->_view->assign("total_stamp_per", $total_stamp_per);
		} catch (Exception $e) {
			$this->_view->assign("error", $e->getMessage());
			return false;
		}
		return true;
	}


	/**
	 * exchangeconfirm
	 */
	public function exchangeconfirmModel() {
		try {
			if (!$this->isLogin()) {
				return "/login";
			}
			
			$dbUser = new DbUser($this->_config);
			$profile = $this->getProfile();
			if ($profile["user_id"] == 1026884 || $profile["user_id"] == 1820095) {
				throw new Exception($this->_getErrorMsg("stamp", "injustice"));
			}

			$this->_view->assign("profile", $profile);
			$this->_view->assign("csrf_token", CSRF::makeToken("stamp", $profile["user_id"]));

			$total_stamp_point = 0;

			// スタンプ合計計算
			$stamps = $dbUser->getStamp(array("user_id" => $profile["user_id"]));
			foreach ($stamps as $stamp) {
				$total_stamp_point += $stamp["stamp_point"];
			}



			// 600ポイント以下はエラー
			if ($total_stamp_point < self::EXCHANGE_STAMP_POINT) {
				throw new Exception($this->_getErrorMsg("stamp", "nopoint"));
			}
		} catch (Exception $e) {
			$this->_view->assign("error", $e->getMessage());
			return false;
		}
		return true;
	}


	/**
	 * exchangecomplete
	 */
	public function exchangecompleteModel() {
		try {
			if ($this->isLogin()) {
				$dbUser = new DbUser($this->_config);
				$profile = $this->getProfile();
				$total_stamp_point = 0;

				if (!CSRF::checkToken($this->_params["csrf_token"], "stamp", $profile["user_id"])) {
					throw new Exception($this->_getErrorMsg("common", "csrf"));
				}

				// スタンプ合計計算
				$stamps = $dbUser->getStamp(array("user_id" => $profile["user_id"]));
				foreach ($stamps as $stamp) {
					$total_stamp_point += $stamp["stamp_point"];
				}

				// 600ポイント以下はエラー
				if ($total_stamp_point < self::EXCHANGE_STAMP_POINT) {
					throw new Exception($this->_getErrorMsg("stamp", "nopoint"));
				} else {
					$dbVpreca = new DbVpreca($this->_config);

					DbCommon::beginTransaction();

					// Vプリカ マスター利用
					$vpreca = $dbVpreca->useVpreca();
					if ($vpreca === false) {
						DbCommon::rollBack();
						throw new Exception($this->_getErrorMsg("stamp", "exchange"));
					}

					// スタンプ消費
					if ($dbUser->useStamp(array("user_id" => $profile["user_id"], "stamp_point" => self::EXCHANGE_STAMP_POINT)) === false) {
						DbCommon::rollBack();
						throw new Exception($this->_getErrorMsg("stamp", "exchange"));
					}

					// Vプリカ ユーザ登録
					$params = array(
						"vpreca_id"	=> $vpreca["vpreca_id"],
						"user_id"		=> $profile["user_id"],
						"vpreca_key"	=> $vpreca["vpreca_key"],
						"vpreca_code"	=> $vpreca["vpreca_code"],
						"vpreca_price"	=> $vpreca["vpreca_price"],
						"stamp_point"	=> self::EXCHANGE_STAMP_POINT,
					);
					if ($dbUser->saveVpreca($params) === false) {
						DbCommon::rollBack();
						throw new Exception($this->_getErrorMsg("stamp", "exchange"));
					}

					DbCommon::commit();

					foreach ($params as $key => $item) {
						$this->_view->assign($key, $item);
					}
				}
			}
		} catch (Exception $e) {
			$this->_view->assign("error", $e->getMessage());
			return false;
		}
		return true;
	}


	/**
	 * history
	 */
	public function historyModel() {
		try {
			if (!$this->isLogin()) {
				return "/login";
			}
			
			$dbUser = new DbUser($this->_config);
			$profile = $this->getProfile();
			// Vプリカ交換情報
			$vprecas = $dbUser->getVpreca(array("user_id" => $profile["user_id"], "is_active" => "true"));
			$this->_view->assign("vprecas", $vprecas);
		} catch (Exception $e) {
			$this->_view->assign("error", $e->getMessage());
			return false;
		}
		return true;
	}





	/** ==================================================
	 * Private Method
	 * ================================================= */





}


?>
