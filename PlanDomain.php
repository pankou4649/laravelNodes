<?php
/**
 * Created by IntelliJ IDEA.
 * User: mitsuru
 * Date: 2016/06/02
 * Time: 10:50
 */
 
namespace App\Domains;
 
use App\AppConst\CommonFunctions;
use App\AppConst\Constants;
use App\Domains\ResponseModels\Plan\PlanDetail;
use App\Domains\ResponseModels\Plan\PlanDetailItem;
use App\Libraries\OraclePaginator;
use App\Models\TermOption;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Log;
 
class PlanDomain extends BaseDomain
{
 
    /**
     * PlanDomain constructor.
     */
    public function __construct()
    {
        parent::__construct();
 
    }
 
    /**
     * @Override
     * @param null $code
     * @param null $type
     * @return array
     */
    public function takeError($code = null, $type = null)
    {
        $code ?? 1;
        $this->setErrorListDomain([
            $code => trans('message.plan_detail.' . $type, [], '', $this->getLang())
        ]);
        return parent::takeError($code, $type);
    }
 
    /**
     * Get rentacar planlist
     *
     * @param $arrCond
     * @return mixed
     */
    public function getPlanList($arrCond)
    {
 
        $plans = null;
        $condEquipdt = [];
        if(!empty($arrCond["options"])){
            $condEquipdt = explode(',', $arrCond["options"]);
            if(!empty($condEquipdt)){
                foreach ($condEquipdt as $key => $value) {
                    # code...
                    if(!empty($value)){
                        if((int)($key) < 10){
                            $condEquipdt[$key] = "000". (int)($key);
                        } else {
                            $condEquipdt[$key] = "00". (int)($key);
                        }
                        if($key === 0){
                            $condEquipdt[$key] = "0001";
                        }
                        if($key === 1){
                            $condEquipdt[$key] = "0002";
                        }
                        // array_push($condEquipdts, $val);
                    }
                }
                // $arrCond['esc'] = $condEquipdt[2];
                if(!empty($condEquipdt[0]) && !empty($condEquipdt[1])){
                    $condEquipdt[0] = "";
                    $condEquipdt[1] = "";
                }
                $arrCond["optionsEquip"] = [
                    $condEquipdt[0],$condEquipdt[1],$condEquipdt[3],
                    $condEquipdt[4],$condEquipdt[5]
                ];
                $arrCond["optionsEquip"] = array_filter($arrCond["optionsEquip"]);
                if(!empty( $arrCond["optionsEquip"])) {
                    $arrCond['equip'] = null;
                }
                $arrCond['pupflag'] =  $condEquipdt[6];
                $arrCond['isStudless'] =  $condEquipdt[7];
            }
            
        }
        $results = $this->getPlansByConditionV2($arrCond, $plans);
        $commision = 0;
        // Check with plantype =1 (b2b)
        $roundFlag = false;
        if ($arrCond['plantype'] == 1 && isset($arrCond['agtcode']) && $arrCond['agtcode']) {
            $cstcode = $arrCond['agtcode'];
            $cst = DB::table('M_CUSTOMER')
                ->where('DELFLAG', '=', 0)
                ->where('REGFLAG', '=', 1)
                ->where('CSTSTAT', '=', 9)
                ->where('USERID', '=', $cstcode)->first();
            if (!$cst) {
                return 'ERR_AGT';
            }
            $commision = $cst->agchrge;
            $roundFlag = true;
        }
        $ratio = (100 + $commision) / 100;
 
        $arrList = [];
 
        if (count($results) == 0) {
            return null;
        }
 
        $arrList['page'] = [
            'recordcount' => $plans->count(),
            'pagecount' => ceil($plans->count() / $arrCond['pagecnt']),
            'pagesize' => count($results)
        ];
 
        $planItems = [];
        foreach ($results as $planItem) {
            $equipmentInfos = null;
            $arrStrEquipdt = CommonFunctions::splitByComma2Arr($planItem['equipdt']);
            if (count($arrStrEquipdt) > 0) {
                $equipmentInfos = [];
                foreach ($arrStrEquipdt as $equipdtItem) {
                    $tmpEqItem = CommonFunctions::splitByTabChar2Arr($equipdtItem, false);
                    array_push($equipmentInfos, [
                        'veqcode' => $tmpEqItem[0],
                        'veqname' => $tmpEqItem[1],
                        'veqgrup' => $tmpEqItem[2],
                        'equipmen' => $tmpEqItem[3],
                    ]);
                }
            }
 
            $optionInfos = null;
            $arrStrOptn = CommonFunctions::splitByComma2Arr($planItem['optndat']);
            if (count($arrStrOptn) > 0) {
                $optionInfos = [];
                $babyFlg = false;
                $childFlg = false;
                $juniorFlg = false;
                foreach ($arrStrOptn as $optnItem) {
                    $tmpOpItem = CommonFunctions::splitByTabChar2Arr($optnItem, false);
                    if (isset($tmpOpItem[5])) {
                        $optionType = (int)$tmpOpItem[5];
                        if ($optionType == 1 && !$babyFlg) {
                            array_push($optionInfos, [
                                'optcode' => 'baby',
                                'vopcode' => trim($tmpOpItem[1]),
                                'optname' => trim($tmpOpItem[2]),
                                'optpric' => (int)(((int)$planItem['fopflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice($this->getOPRIC($tmpOpItem, $arrCond) * $ratio, $roundFlag)),
                                'vopflag' => 1
                            ]);
                            $babyFlg = true;
                        }
                        if ($optionType == 2 && !$childFlg) {
                            array_push($optionInfos, [
                                'optcode' => 'baby',
                                'vopcode' => trim($tmpOpItem[1]),
                                'optname' => trim($tmpOpItem[2]),
                                'optpric' => (int)(((int)$planItem['fopflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice($this->getOPRIC($tmpOpItem, $arrCond) * $ratio, $roundFlag)),
                                'vopflag' => 2
                            ]);
                            $childFlg = true;
                        }
                        if ($optionType == 3 && !$juniorFlg) {
                            array_push($optionInfos, [
                                'optcode' => 'junior',
                                'vopcode' => trim($tmpOpItem[1]),
                                'optname' => trim($tmpOpItem[2]),
                                'optpric' => (int)(((int)$planItem['fopflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice($this->getOPRIC($tmpOpItem, $arrCond) * $ratio, $roundFlag)),
                                'vopflag' => 3
                            ]);
                            $juniorFlg = true;
                        }
                        if ($optionType == 0) {
                            array_push($optionInfos, [
                                'optcode' => trim($tmpOpItem[0]),
                                'vopcode' => trim($tmpOpItem[1]),
                                'optname' => trim($tmpOpItem[2]),
                                'optpric' => (int)(((int)$planItem['fopflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice($this->getOPRIC($tmpOpItem, $arrCond) * $ratio, $roundFlag)),
                                'vopflag' => 0
                            ]);
                        }
                    } else {
                        continue;
                    }
                }
            }
            $tmp = [
                'jp' => "時間",
                'en' => " hours",
                'cn' => '小时',
                'tw' => '小時',
                'kr' => '시간'
            ];
            $rentTimePrices = [
                ['time' => '3' . $tmp[$this->getLang()], 'price' => CommonFunctions::roundComisionPrice((int)$planItem['price03'] * $ratio, $roundFlag)],
                ['time' => '6' . $tmp[$this->getLang()], 'price' => CommonFunctions::roundComisionPrice((int)$planItem['price06'] * $ratio, $roundFlag)],
            ];
            $escpric = $arrCond['plantype'] == 1 ? 0 : (($planItem['escflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice((int)$planItem['p2'] * $ratio, $roundFlag));
            $escflag = $arrCond['plantype'] == 1 ? 3 : $planItem['escflag'];
            $payType = (((int) $planItem['ispaycod'] == 1)) ? '0,' : '';
            $payType .= (((int) $planItem['ispaycre'] == 1 && (int) $planItem['creditflg'] == 1) && (int)$planItem['selcars'] !== 0) ? '1,' : '';
            $payType .= (((int) $planItem['ispayarv'] == 1)) ? '2,' : '';
            $payType = substr($payType, 0, -1);
            if(((int)$planItem['selcars'] === 0) && ((int) $planItem['ispaycre'] == 1) && ((int) $planItem['ispaycod'] == 0)){
                continue;
            }
            $planTmp = [
                'plncode' => $planItem['plncode'],
                'plnname' => $planItem['plnname'],
                'plncomt' => $planItem['plncomt'],
                'brdcode' => $planItem['brdcode'],
                'brdname' => $planItem['brdname'],
                'cmpcode' => $planItem['cmpcode'],
                'cmpname' => ($this->getLang() == 'jp' ? $planItem['cmpname'] : ''),
                'cmpimag' => $planItem['cmpimag'],
                'shpcode' => $planItem['shpcode'],
                'shpname' => $planItem['shpname'],
                'pupflag' => (int)$planItem['pupflag'],
                'svhcode' => $planItem['svhcode'],
                'vehcode' => $planItem['vehcode'],
                'vehname' => $planItem['vehname'],
                'vehimag' => ($planItem['plnimg1'] != '' ? $planItem['plnimg1'] : $planItem['imgfil1']),
                'psncapa' => $planItem['psncapa'],
                'stknum' => $planItem['selcars'],
                'paytype' => is_bool($payType) ? 0 : $payType,
                'clsflag' => $planItem['clsflag'],
                'vclcode' => $planItem['vclcode'],
                'vclname' => $planItem['vclname'],
                'eclcode' => $planItem['eclcode'],
                'eclname' => $planItem['eclname'],
                'optflag' => (int)$planItem['optflag'],
                'gofflag' => (int)$planItem['gofflag'],
                'EquipmentInfoList' => $equipmentInfos,
                'address' => $planItem['addr1'],
                'access' => $planItem['acccomt'],
                'rsvstat' => ((int)$planItem['zaiko'] > 0 ? "1" : "0"),
                'escflag' => $escflag,
                'escpric' => $escpric,
                'baspric' => CommonFunctions::roundComisionPrice((int)$planItem['p1'] * $ratio, $roundFlag) - abs((int)$planItem['p5']),
//                'dispric' => (int)$planItem['p5'],
                'dispric' => 0,
                'dcacf' =>  $planItem['dcacf'],
                'dropoffpric' => ((int)$planItem['gofflag'] != 1 && $planItem['droffprice'] != '') ? CommonFunctions::roundComisionPrice((int)$planItem['p3'] * $ratio, $roundFlag) : null,
                'OptionInfoList' => $optionInfos,
                'RentTimePrice' => $rentTimePrices
            ];
            // b2b discount price
            if ($arrCond['plantype'] == 1) {
                $planTmp['dispric'] = 0;
            }
            array_push($planItems, $planTmp);
        }
        $arrList['plan'] = $planItems;
        return $arrList;
    }
 
    /**
     * Get detail of plan by conditions
     *
     * @param $arrCond
     * @param $extraCols
     * @return null|array
     */
    public function getPlanDetail($arrCond, $extraCols = [], $lang = null)
    {
        $lang = $lang ? $lang : $this->getLang();
        $plans = null;
        $commision = 0;
        $nocom = $extraCols['nocom'] ?? false;
        if (isset($extraCols['nocom'])) {
            unset($extraCols['nocom']);
        }
 
        $plantype = $arrCond['plantype'] ?? 0;
        if ($plantype == 1) {
            $lang = $this->getLang();
        }
        $roundFlag = false;
 
        $agtcode = $arrCond['agtcode'] ?? null;
        // Check with plantype =1 (b2b)
        if ($plantype == 1 && $agtcode) {
            $cstcode = $agtcode;
            $cst = DB::table('M_CUSTOMER')
                ->where('DELFLAG', '=', 0)
                ->where('REGFLAG', '=', 1)
                ->where('CSTSTAT', '=', 9)
                ->where('USERID', '=', $cstcode)->first();
            if (!$cst) {
                return 'ERR_AGT';
            }
            $commision = $cst->agchrge;
            if (isset($arrCond['apirequest']) && $arrCond['apirequest'] == true) {
                $roundFlag = true;
            }
        }
        if (isset($arrCond['apirequest'])) {
            unset($arrCond['apirequest']);
        }
        $ratio = (100 + $commision) / 100;
        if ($nocom == true) {
            $ratio = 1;
            $roundFlag = false;
        }
 
        $results = $this->getPlansByConditionV2($arrCond, $plans);
        if (count($results) == 0) {
            return null;
        }
 
        $planItem = $results[0];
        $equipmentInfos = null;
        $veqNameOrigin = "";
        $arrStrEquipdt = CommonFunctions::splitByComma2Arr($planItem['equipdt']);
        if (count($arrStrEquipdt) > 0) {
            $equipmentInfos = [];
            foreach ($arrStrEquipdt as $equipdtItem) {
                $tmpEqItem = CommonFunctions::splitByTabChar2Arr($equipdtItem, false);
                array_push($equipmentInfos, [
                    'veqcode' => $tmpEqItem[0],
                    'veqname' => $tmpEqItem[1],
                    'veqgrup' => $tmpEqItem[2],
                    'equipmen' => $tmpEqItem[3],
                ]);
                $veqNameOrigin .= $tmpEqItem[4] . ",";
            }
        }
 
        $optionInfos = null;
        $arrStrOptn = CommonFunctions::splitByComma2Arr($planItem['optndat']);
        if (count($arrStrOptn) > 0) {
            $optionInfos = [];
            $babyFlg = false;
            $childFlg = false;
            $juniorFlg = false;
            foreach ($arrStrOptn as $optnItem) {
                $tmpOpItem = CommonFunctions::splitByTabChar2Arr($optnItem, false);
                if (isset($tmpOpItem[5])) {
                    $optionType = (int)$tmpOpItem[5];
                    if ($optionType == 1 && !$babyFlg) {
                        array_push($optionInfos, [
                            'optcode' => 'baby',
                            'vopcode' => trim($tmpOpItem[1]),
                            'optname' => trim($tmpOpItem[2]),
                            'optpric' => (int)(((int)$planItem['fopflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice($this->getOPRIC($tmpOpItem, $arrCond) * $ratio, $roundFlag)),
                            'vopflag' => 1
                        ]);
                        $babyFlg = true;
                    }
                    if ($optionType == 2 && !$childFlg) {
                        array_push($optionInfos, [
                            'optcode' => 'baby',
                            'vopcode' => trim($tmpOpItem[1]),
                            'optname' => trim($tmpOpItem[2]),
                            'optpric' => (int)(((int)$planItem['fopflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice($this->getOPRIC($tmpOpItem, $arrCond) * $ratio, $roundFlag)),
                            'vopflag' => 2
                        ]);
                        $childFlg = true;
                    }
                    if ($optionType == 3 && !$juniorFlg) {
                        array_push($optionInfos, [
                            'optcode' => 'junior',
                            'vopcode' => trim($tmpOpItem[1]),
                            'optname' => trim($tmpOpItem[2]),
                            'optpric' => (int)(((int)$planItem['fopflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice($this->getOPRIC($tmpOpItem, $arrCond) * $ratio, $roundFlag)),
                            'vopflag' => 3
                        ]);
                        $juniorFlg = true;
                    }
                    if ($optionType == 0) {
                        array_push($optionInfos, [
                            'optcode' => trim($tmpOpItem[0]),
                            'vopcode' => trim($tmpOpItem[1]),
                            'optname' => trim($tmpOpItem[2]),
                            'optpric' => (int)(((int)$planItem['fopflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice($this->getOPRIC($tmpOpItem, $arrCond) * $ratio, $roundFlag)),
                            'vopflag' => 0
                        ]);
                    }
                } else {
                    continue;
                }
            }
        }
        $termOption = $this->getB2CTermOption(Carbon::instance($arrCond['strdate']), Carbon::instance($arrCond['enddate']), $planItem['brdcode'], $planItem['shpcode'], $planItem['trmcode'], $arrCond['plantype'], $arrCond['langflag']);
 
        $appDomain = new ApplicationDomain();
        $trafficInfos = $appDomain->getTrafficInfo();
        $creditName = $appDomain->getCreditByBrandAndShop($planItem['brdcode'], $planItem['shpcode']);
        // Get cancel policy
        $clxPolicies = $this->getCancelPolicy($planItem['cmpcode'], $planItem['brdcode'], $this->getLang());
        $escpric = ($planItem['escflag'] == 1) ? 0 : CommonFunctions::roundComisionPrice((int)$planItem['p2'] * $ratio, $roundFlag);
        $escpric = $plantype == 1 ? 0 : $escpric;
        $escflag = $plantype == 1 ? 3 : $planItem['escflag'];
        $payType = (((int) $planItem['ispaycod'] == 1)) ? '0,' : '';
        $payType .= (((int) $planItem['ispaycre'] == 1 && (int) $planItem['creditflg'] == 1) && (int)$planItem['selcars'] !== 0) ? '1,' : '';
        $payType .= (((int) $planItem['ispayarv'] == 1)) ? '2,' : '';
        $payType = substr($payType, 0, -1);
        if(((int)$planItem['selcars'] === 0) && ((int) $planItem['ispaycre'] == 1) && ((int) $planItem['ispaycod'] == 0)){
            return null;
        }
        if ($extraCols) {
            $planTmp = [
                'plncode' => $planItem['plncode'],
                'plnname' => $planItem['plnname'],
                'plncomt' => $planItem['plncomt'],
                'brdcode' => $planItem['brdcode'],
                'brdname' => $planItem['brdname'],
                'cmpcode' => $planItem['cmpcode'],
                'cmpname' => ($lang == 'jp' ? $planItem['cmpname'] : ''),
                'cmpimag' => $planItem['cmpimag'],
                'hidecmt' => (int)$planItem['hidcomt'],
                'shpcode' => $planItem['shpcode'],
                'shpname' => $planItem['shpname'],
                'shptel' => $planItem['shoptel'],
                'pupflag' => (int)$planItem['pupflag'],
                'pupcomt' => $planItem['pupcomt'],
                'vehcode' => $planItem['vehcode'],
                'vehname' => $planItem['vehname'],
                'clsflag' => $planItem['clsflag'],
                'vehimag' => ($planItem['plnimg1'] != '' ? $planItem['plnimg1'] : $planItem['imgfil1']),
                'psncapa' => $planItem['psncapa'],
                'vclname' => $planItem['vclname'],
                'eclname' => $planItem['eclname'],
                'optflag' => (int)$planItem['optflag'],
                'gofflag' => (int)$planItem['gofflag'],
                'svhcode' => $planItem['svhcode'],
                'stknum' => (int)$planItem['selcars'],
                'paytype' => is_bool($payType) ? 0 : $payType,
                'EquipmentInfoList' => $equipmentInfos,
                'address' => $planItem['addr1'],
                'access' => $planItem['acccomt'],
                'rsvstat' => ((int)$planItem['zaiko'] > 0 ? "1" : "0"),
                'escflag' => $escflag,
                'baspric' => CommonFunctions::roundComisionPrice((int)$planItem['p1'] * $ratio, $roundFlag),
                'escpric' => $escpric,
                'dispric' => (int)$planItem['p5'],
                'discountRate' => (int)$planItem['dscpric'],
                'dcnkubn' => (int)$planItem['dcnkubn'],
                'dcacf' => (int)$planItem['dcacf'],
                'buypric' => (int)$planItem['buypric'],
                'plnnameorigin' => $planItem['plnnameorigin'],
                'vclnameorigin' => $planItem['vclnameorigin'],
                'eclnameorigin' => $planItem['eclnameorigin'],
                'vehnameorigin' => $planItem['vehnameorigin'],
                'brdnameorigin' => $planItem['brdnameorigin'],
                'shpnameorigin' => $planItem['shpnameorigin'],
                'veqnameorigin' => substr($veqNameOrigin, 0, -1),
 
                'dropoffpric' => ((int)$planItem['gofflag'] != 1 && $planItem['droffprice'] != '') ? CommonFunctions::roundComisionPrice((int)$planItem['p3'] * $ratio, $roundFlag) : null,
 
                'OptionInfoList' => $optionInfos,
                'TrafficInfoList' => $trafficInfos,
                'credit' => $creditName,
 
                // 問合せ可能店舗の制御
                'rsvinqflg' => $this->isInquiry($planItem['brdcode']) ? '1' : '0',
                'termOption' => $termOption,
            ];
            foreach ($extraCols as $key => $value) {
                $planTmp[$key] = $planItem[$value];
            }
            foreach ($clxPolicies as $key => $val) {
                $planTmp[$key] = $val;
            }
            // Set discount price = 0 b2b
            if ($plantype == 1) {
                $planTmp['dispric'] = 0;
            }
            $arrPlanDetail['plandetail'] = $planTmp;
        } else {
            $planDetailItem = new PlanDetailItem();
            $planDetailItem->plncode = $planItem['plncode'];
            $planDetailItem->plnname = $planItem['plnname'];
            $planDetailItem->plncomt = $planItem['plncomt'];
            $planDetailItem->brdcode = $planItem['brdcode'];
            $planDetailItem->brdname = $planItem['brdname'];
            $planDetailItem->cmpcode = $planItem['cmpcode'];
            $planDetailItem->cmpname = ($lang == 'jp' ? $planItem['cmpname'] : '');
            $planDetailItem->cmpimag = $planItem['cmpimag'];
            $planDetailItem->hidecmt = (int)$planItem['hidcomt'];
            $planDetailItem->shpcode = $planItem['shpcode'];
            $planDetailItem->shpname = $planItem['shpname'];
            $planDetailItem->shptel = $planItem['shoptel'];
            $planDetailItem->pupflag = (int)$planItem['pupflag'];
            $planDetailItem->pupcomt = $planItem['pupcomt'];
            $planDetailItem->vehcode = $planItem['vehcode'];
            $planDetailItem->vehname = $planItem['vehname'];
            $planDetailItem->clsflag = $planItem['clsflag'];
            $planDetailItem->vehimag = ($planItem['plnimg1'] != '' ? $planItem['plnimg1'] : $planItem['imgfil1']);
            $planDetailItem->psncapa = $planItem['psncapa'];
            $planDetailItem->vclname = $planItem['vclname'];
            $planDetailItem->eclname = $planItem['eclname'];
            $planDetailItem->optflag = (int)$planItem['optflag'];
            $planDetailItem->gofflag = (int)$planItem['gofflag'];
            $planDetailItem->svhcode = $planItem['svhcode'];
            $planDetailItem->stknum = (int)$planItem['selcars'];
            $planDetailItem->EquipmentInfoList = $equipmentInfos;
            $planDetailItem->address = $planItem['addr1'];
            $planDetailItem->access = $planItem['acccomt'];
            $planDetailItem->rsvstat = ((int)$planItem['zaiko'] > 0 ? "1" : "0");
            $planDetailItem->escflag = $escflag;
            $planDetailItem->baspric = CommonFunctions::roundComisionPrice((int)$planItem['p1'] * $ratio, $roundFlag);
            $planDetailItem->escpric = $escpric;
            $planDetailItem->dispric = (int)$planItem['dscpric'];
            $planDetailItem->dcnkubn = (int)$planItem['dcnkubn'];
            $planDetailItem->dcacf = (int)$planItem['dcacf'];
            $planDetailItem->discountRate = (int)$planItem['dscpric'];
            
            $planDetailItem->dropoffpric = ((int)$planItem['gofflag'] != 1 && $planItem['droffprice'] != '') ? CommonFunctions::roundComisionPrice((int)$planItem['p3'] * $ratio, $roundFlag) : null;
 
            //Price detail
            $planDetailItem->pricedetail = [
                '3hours' => (int)$planItem[self::getRankPriceName('price03', ($arrCond['plantype'] == 1))],
                '6hours' => (int)$planItem[self::getRankPriceName('price06', ($arrCond['plantype'] == 1))],
                '12hours' => (int)$planItem[self::getRankPriceName('price12', ($arrCond['plantype'] == 1))],
                '24hours' => (int)$planItem[self::getRankPriceName('price24', ($arrCond['plantype'] == 1))],
                'ex1hour' => (int)$planItem[self::getRankPriceName('priceex', ($arrCond['plantype'] == 1))],
                'ex1day' => (int)$planItem[self::getRankPriceName('pricea1', ($arrCond['plantype'] == 1))],
            ];
 
            $planDetailItem->OptionInfoList = $optionInfos;
            $planDetailItem->TrafficInfoList = $trafficInfos;
            $planDetailItem->credit = $creditName;
            $planDetailItem->paytype = is_bool($payType) ? 0 : $payType;
 
            $planDetailItem->termOption = $termOption;
 
            // 問合せ可能店舗の制御
            $planDetailItem->rsvinqflg = $this->isInquiry($planItem['brdcode']) ? '1' : '0';
            foreach ($clxPolicies as $key => $val) {
                $planDetailItem->$key = $val;
            }
            // Set discount price = 0 b2b
            if ($plantype == 1) {
                $planDetailItem->dispric = 0;
            }
            $planDetail = new PlanDetail();
            $planDetail->plandetail = $planDetailItem;
            $arrPlanDetail = $planDetail->instance();
        }
 
        return $arrPlanDetail;
 
    }
    
    /**
     * Get list shops are available
     *
     * @param $arrCond
     * @return array
     */
    private function getRequiredShopList($arrCond)
    {
 
        $shopsAvaiable = [];
 
        $shopColumns = [
            "S.BRDCODE", "S.SHPCODE", "S.OPNTIM1", "S.CLOTIM1", "S.OPNTIM2", "S.CLOTIM2", "S.OPNTIM3", "S.CLOTIM3", "S.P1OPNDT",
            "S.P1CLODT", "S.P1OPTM1", "S.P1CLTM1", "S.P1OPTM2", "S.P1CLTM2", "S.P1OPTM3", "S.P1CLTM3", "S.P2OPNDT", "S.P2CLODT",
            "S.P2OPTM1", "S.P2CLTM1", "S.P2OPTM2", "S.P2CLTM2", "S.P2OPTM3", "S.P2CLTM3", "S.P3OPNDT", "S.P3CLODT", "S.P3OPTM1",
            "S.P3CLTM1", "S.P3OPTM2", "S.P3CLTM2", "S.P3OPTM3", "S.P3CLTM3", "S.P4OPNDT", "S.P4CLODT", "S.P4OPTM1", "S.P4CLTM1",
            "S.P4OPTM2", "S.P4CLTM2", "S.P4OPTM3", "S.P4CLTM3", "S.CLOFLG1", "S.CLOFLG2", "S.CLOFLG3", "S.CLOFLG4", "S.CLOFLG5",
            "S.CLOFLG6", "S.CLOFLG7", "S.CLOFLG8", "S.SPCLDT0", "S.SPCLDT1", "S.SPCLDT2", "S.SPCLDT3", "S.SPCLDT4", "S.SPCLDT5",
            "S.SPCLDT6", "S.SPCLDT7", "S.SPCLDT8", "S.SPCLDT9", "S.FULLFLG", "S.USEFLG1", "S.USEFLG2", "S.USEFLG3", "S.USEFLG4",
            "S.TEJMAID", "S.TEJMAIT", "S.TIME_MIN", "S.TIME_OPTION"
        ];
 
        $queryReqShop = DB::table('M_SHOP S')->distinct();
 
        if ($arrCond['seccode'] != '') {
            $tmpLandmark1 = DB::raw("(SELECT ASCCODE FROM M_LANDMARK WHERE SECCODE='" . $arrCond['seccode'] . "' AND DELFLAG='0') L1");
            $queryReqShop->join($tmpLandmark1, function ($join) {
                $join->on('S.ASCCOD0', '=', 'L1.ASCCODE')
                    ->orOn('S.ASCCOD1', '=', 'L1.ASCCODE')
                    ->orOn('S.ASCCOD2', '=', 'L1.ASCCODE')
                    ->orOn('S.ASCCOD3', '=', 'L1.ASCCODE')
                    ->orOn('S.ASCCOD4', '=', 'L1.ASCCODE');
            });
        }
 
        if ($arrCond['dropoff'] == 0) {
            if ($arrCond['seccode2'] != '' || count($arrCond['asccode2']) > 0) {
                $queryReqShop->leftJoin('M_NORISUTE N', function ($join) {
                    $join->on('S.BRDCODE', '=', 'N.BRDCODE')
                        ->on('S.SHPCODE', '=', 'N.SHPCOD1');
                })->leftJoin('M_SHOP S2', function ($join) {
                    $join->on('S2.BRDCODE', '=', 'N.BRDCODE')
                        ->on('S2.SHPCODE', '=', 'N.SHPCOD2');
                });
                if ($arrCond['seccode2'] != '' && ($arrCond['seccode'] !== $arrCond['seccode2'])) {
                    $tmpLandmark2 = DB::raw("(SELECT ASCCODE FROM M_LANDMARK WHERE SECCODE='" . $arrCond['seccode2'] . "' AND DELFLAG='0') L2");
                    $queryReqShop->leftJoin($tmpLandmark2, function ($join) {
                        $join->on('S2.ASCCOD0', '=', 'L2.ASCCODE')
                            ->orOn('S2.ASCCOD1', '=', 'L2.ASCCODE')
                            ->orOn('S2.ASCCOD2', '=', 'L2.ASCCODE')
                            ->orOn('S2.ASCCOD3', '=', 'L2.ASCCODE')
                            ->orOn('S2.ASCCOD4', '=', 'L2.ASCCODE');
                    });
                }
            }
        }
 
        $queryReqShop->where('S.DELFLAG', '0');
 
        if ($arrCond['plantype'] === 0) {
            $queryReqShop->where('S.SYAGFLG', 1);
        }
        if ($arrCond['plantype'] === 1) {
            $queryReqShop->where('S.B2BFLAG', 1);
        }
        if ($arrCond['plantype'] === 2) {
            $queryReqShop->where('S.DNPFLAG', 1);
        }
 
        if ($arrCond['dropoff'] === 0) {
            if (count($arrCond['asccode2']) > 0) {
                $asccode = $arrCond['asccode'];
                $asccode2 = $arrCond['asccode2'];
                $queryReqShop->where(function ($q2) use ($asccode, $asccode2) {
                    $isFirstRun1 = true;
                    foreach ($asccode2 as $asc2Item) {
                        $dbl_flg = true;
                        if (count($asccode) > 0) {
                            foreach ($asccode as $ascItem) {
                                if ($ascItem == $asc2Item) {
                                    $dbl_flg = false;
                                }
                            }
                        }
                        if ($dbl_flg) {
                            if ($isFirstRun1) {
                                $q2->where(function ($q) use ($asc2Item) {
                                    $q->where('S2.ASCCOD0', '=', $asc2Item)
                                        ->orWhere('S2.ASCCOD1', '=', $asc2Item)
                                        ->orWhere('S2.ASCCOD2', '=', $asc2Item)
                                        ->orWhere('S2.ASCCOD3', '=', $asc2Item)
                                        ->orWhere('S2.ASCCOD4', '=', $asc2Item);
                                });
                                $isFirstRun1 = false;
                            } else {
                                $q2->orWhere(function ($q) use ($asc2Item) {
                                    $q->where('S2.ASCCOD0', '=', $asc2Item)
                                        ->orWhere('S2.ASCCOD1', '=', $asc2Item)
                                        ->orWhere('S2.ASCCOD2', '=', $asc2Item)
                                        ->orWhere('S2.ASCCOD3', '=', $asc2Item)
                                        ->orWhere('S2.ASCCOD4', '=', $asc2Item);
                                });
                            }
                        }
                    }
                });
            }
        }
        if (count($arrCond['asccode']) > 0) {
            $ascItems = $arrCond['asccode'];
            $queryReqShop->where(function ($qu) use ($ascItems) {
                $isFirstRun2 = true;
                foreach ($ascItems as $ascItem) {
                    if ($isFirstRun2) {
                        $qu->where(function ($q) use ($ascItem) {
                            $q->where('S.ASCCOD0', '=', $ascItem)
                                ->orWhere('S.ASCCOD1', '=', $ascItem)
                                ->orWhere('S.ASCCOD2', '=', $ascItem)
                                ->orWhere('S.ASCCOD3', '=', $ascItem)
                                ->orWhere('S.ASCCOD4', '=', $ascItem);
                        });
                        $isFirstRun2 = false;
                    } else {
                        $qu->orWhere(function ($q) use ($ascItem) {
                            $q->where('S.ASCCOD0', '=', $ascItem)
                                ->orWhere('S.ASCCOD1', '=', $ascItem)
                                ->orWhere('S.ASCCOD2', '=', $ascItem)
                                ->orWhere('S.ASCCOD3', '=', $ascItem)
                                ->orWhere('S.ASCCOD4', '=', $ascItem);
                        });
                    }
                }
            });
        }
 
        // plan list API の場合、配列で来る事を想定。Plan Detail API は単一コードのみ
        if ($arrCond['brdcode']) {
            if (is_array($arrCond['brdcode'])) {
                $queryReqShop->whereIn('S.BRDCODE', $arrCond['brdcode']);
            } else {
                $queryReqShop->where('S.BRDCODE', $arrCond['brdcode']);
            }
        }
 
        if ($arrCond['shpcode'] !== '') {
            $queryReqShop->where('S.SHPCODE', '=', $arrCond['shpcode']);
        }
 
        $queryReqShop->select($shopColumns)->orderBy('S.BRDCODE', 'ASC')
            ->orderBy('S.SHPCODE', 'ASC');
 
        $reqShops = $queryReqShop->get()->toArray();
 
        if (count($reqShops) > 0) {
            //$strdate = Carbon::instance($arrCond['strdate']);
            //$enddate = Carbon::instance($arrCond['enddate']);
            $appDomain = new ApplicationDomain();
            $holidays = $appDomain->getHolidaysOnDateRange(Carbon::instance($arrCond['strdate']), Carbon::instance($arrCond['enddate']));
 
            foreach ($reqShops as $shopItem) {
                if ($this->getIsShopEnable((array)$shopItem, Carbon::instance($arrCond['strdate']), Carbon::instance($arrCond['enddate']), $holidays)) {
                    array_push($shopsAvaiable, $shopItem);
                }
            }
        }
 
        return $shopsAvaiable;
    }
 
    /**
     * Convert to string using in SQL 'IN'
     *
     * @param $arrRequiredShopList
     * @return string
     */
    private function convertShopListSqlIn($arrRequiredShopList)
    {
        $str = "";
        foreach ($arrRequiredShopList as $shop) {
            $str .= "('" . $shop->brdcode . "','" . $shop->shpcode . "'),";
        }
        $str = rtrim($str, ',');
        return $str;
    }
 
    /**
     * Check shop is enable in range time
     *
     * @param array $shopItem
     * @param Carbon $strdate
     * @param Carbon $enddate
     * @param $holidays
     * @return bool
     */
    private function getIsShopEnable(array $shopItem, Carbon $strdate, Carbon $enddate, $holidays)
    {
        $now = Carbon::now();
        $tjdttm = $now->copy();
        //30分の場合は切り上げる
        $whm1 = ($strdate->hour * 100) + $strdate->minute;
        $whm2 = ($enddate->hour * 100) + $enddate->minute;
 
        //手仕舞のチェック（貸出日）
        if ((int)$shopItem["tejmaid"] >= 0) {
            $tjdttm = $tjdttm->addDays((int)$shopItem["tejmaid"]);
        }
 
        if ((int)$shopItem["tejmait"] >= 0) {
            $tjdttm = $tjdttm->addHours((int)$shopItem["tejmait"]);
        }
 
        // UPDATE FIX TIME OPTION
        if ((int)$shopItem["time_min"] >= 0) {
            $tjdttm = $tjdttm->addMinutes((int)$shopItem["time_min"]);
        }
 
        if ((int)$shopItem["time_option"] > 0) {
            $tjdttm = $now->today()->copy();
            if ((int)$shopItem["tejmaid"] > 0) {
                $tjdttm = $tjdttm->addDays((int)$shopItem["tejmaid"]);
            }
            if ($tjdttm->gt($strdate->startOfDay())) {
                return false;
            } else {
                if ($tjdttm->isSameDay($strdate)) {
                    if ((int)$shopItem["tejmait"] >= 0) {
                        if ($now->hour > (int)$shopItem["tejmait"]) {
                            return false;
                        } else if ($now->hour == (int)$shopItem["tejmait"]) {
                            if ((int)$shopItem["time_min"] >= 0) {
                                if ($now->minute >= (int)$shopItem["time_min"]) {
                                    return false;
                                }
                            }
                        }
                    }
                }
            }
        } else {
            if ($tjdttm->gt($strdate)) {
                return false;
            }
        }
        // END UPDATE FIX TIME OPTION
 
        $dwk = "";
 
        //店舗利用可否のチェック
        for ($d = 0; $d <= 1; ++$d) {
            $ymd2 = null;
            if ($d == 0) {
                $ymd2 = $strdate->copy(); //貸出日
            } else {
                $ymd2 = $enddate->copy(); //返却日
            }
 
            //曜日の設定
            $dwk = ($ymd2->dayOfWeek + 1) . "";
            //休業曜日のチェック
            if ((int)$shopItem["cloflg" . $dwk] == 1) {
                return false;
            }
 
            //祝日の場合
            for ($c = 0; $c < count($holidays) - 1; ++$c) {
                if ((int)$ymd2->format('Ymd') === (int)$holidays[$c]) {
                    if ((int)$shopItem["cloflg8"] == 1) {
                        return false;
                    } else {
                        $dwk = "1";
                    }
                }
            }
 
            $int_mmdd = (int)$ymd2->format('md');
            //特定休業日のチェック
            for ($c = 0; $c < 5; ++$c) {
                if ((int)$shopItem["spcldt" . $c] === $int_mmdd) {
                    return false;
                }
            }
 
            //特定休業日（範囲）のチェック
            if ($shopItem["spcldt6"] != '' && $shopItem["spcldt7"] != '') {
                $SPCLDT6 = (int)$shopItem["spcldt6"];
                $SPCLDT7 = (int)$shopItem["spcldt7"];
                if ($SPCLDT6 === $SPCLDT7) {
                    if ($SPCLDT6 === $int_mmdd) {
                        return false;
                    }
                } else if ($SPCLDT6 < $SPCLDT7) {
                    if (($SPCLDT6 <= $int_mmdd) && ($SPCLDT7 >= $int_mmdd)) {
                        return false;
                    }
                } else {
                    if ((($SPCLDT6 <= $int_mmdd) && (1231 >= $int_mmdd)) || ((101 <= $int_mmdd) && ($SPCLDT7 >= $int_mmdd))) {
                        return false;
                    }
                }
            }
 
            if ($shopItem["spcldt8"] != '' && $shopItem["spcldt9"] != '') {
                $SPCLDT8 = (int)$shopItem["spcldt8"];
                $SPCLDT9 = (int)$shopItem["spcldt9"];
                if ($SPCLDT8 === $SPCLDT9) {
                    if ($SPCLDT8 === $int_mmdd) {
                        return false;
                    }
                } else if ($SPCLDT8 < $SPCLDT9) {
                    if (($SPCLDT8 <= $int_mmdd) && ($SPCLDT9 >= $int_mmdd)) {
                        return false;
                    }
                } else {
                    if ((($SPCLDT8 <= $int_mmdd) && (1231 > $int_mmdd)) || ((101 < $int_mmdd) && ($SPCLDT9 >= $int_mmdd))) {
                        return false;
                    }
                }
            }
 
            $period = "";
            //営業時間のチェック
            if ((int)$shopItem["fullflg"] !== 1) {
                //24時間店舗以外の場合
                $period = "0";
                if (((int)$shopItem["useflg4"] === 1) && (((((int)$shopItem["p4opndt"]) > ((int)$shopItem["p4clodt"])) && ($int_mmdd >= ((int)$shopItem["p4opndt"]) || $int_mmdd <= ((int)$shopItem["p4clodt"]))) || ((((int)$shopItem["p4opndt"]) <= ((int)$shopItem["p4clodt"])) && ($int_mmdd >= ((int)$shopItem["p4opndt"]) && $int_mmdd <= ((int)$shopItem["p4clodt"]))))) {
                    $period = "4";
                }
                if (($period == "0") && ((int)$shopItem["useflg3"] === 1) && (((((int)$shopItem["p3opndt"]) > ((int)$shopItem["p3clodt"])) && ($int_mmdd >= ((int)$shopItem["p3opndt"]) || $int_mmdd <= ((int)$shopItem["p3clodt"]))) || ((((int)$shopItem["p3opndt"]) <= ((int)$shopItem["p3clodt"])) && ($int_mmdd >= ((int)$shopItem["p3opndt"]) && $int_mmdd <= ((int)$shopItem["p3clodt"]))))) {
                    $period = "3";
                }
                if (($period == "0") && ((int)$shopItem["useflg2"] === 1) && (((((int)$shopItem["p2opndt"]) > ((int)$shopItem["p2clodt"])) && (($int_mmdd >= (int)$shopItem["p2opndt"]) || ($int_mmdd <= (int)$shopItem["p2clodt"]))) || ((((int)$shopItem["p2opndt"]) <= ((int)$shopItem["p2clodt"])) && (($int_mmdd >= (int)$shopItem["p2opndt"]) && ($int_mmdd <= (int)$shopItem["p2clodt"]))))) {
                    $period = "2";
                }
                if (($period == "0") && ((int)$shopItem["useflg1"] === 1) && (((((int)$shopItem["p1opndt"]) > ((int)$shopItem["p1clodt"])) && (($int_mmdd >= (int)$shopItem["p1opndt"]) || ($int_mmdd <= (int)$shopItem["p1clodt"]))) || ((((int)$shopItem["p1opndt"]) <= ((int)$shopItem["p1clodt"])) && (($int_mmdd >= (int)$shopItem["p1opndt"]) && ($int_mmdd <= (int)$shopItem["p1clodt"]))))) {
                    $period = "1";
                }
 
                $opntm = 0;
                $clotm = 0;
 
                if ($period === "0") {
                    switch ($dwk) {
                        case "1"://日曜
                            $opntm = (int)$shopItem["opntim3"];
                            $clotm = (int)$shopItem["clotim3"];
                            break;
                        case "7"://土曜
                            $opntm = (int)$shopItem["opntim2"];
                            $clotm = (int)$shopItem["clotim2"];
                            break;
                        default://平日
                            $opntm = (int)$shopItem["opntim1"];
                            $clotm = (int)$shopItem["clotim1"];
                            break;
                    }
                } else {
                    switch ($dwk) {
                        case "1"://日曜
                            $opntm = (int)$shopItem["p" . $period . "optm3"];
                            $clotm = (int)$shopItem["p" . $period . "cltm3"];
                            break;
                        case "7"://土曜
                            $opntm = (int)$shopItem["p" . $period . "optm2"];
                            $clotm = (int)$shopItem["p" . $period . "cltm2"];
                            break;
                        default://平日
                            $opntm = (int)$shopItem["p" . $period . "optm1"];
                            $clotm = (int)$shopItem["p" . $period . "cltm1"];
                            break;
                    }
                }
 
                if (($whm1 < $opntm || $whm1 > $clotm) && $d == 0) {
                    return false;
                }
 
                if (($whm2 < $opntm || $whm2 > $clotm) && $d == 1) {
                    return false;
                }
            }
            unset($ymd2);
        }
        return true;
    }
 
    /**
     * Get Basic Price of One plan
     *
     * @param $arrPlanItem
     * @param Carbon $frmDate
     * @param Carbon $toDate
     * @param $ESCMode
     * @param $b2bflag
     * @return float|int|mixed
     */
    private function getPriceBasic($arrPlanItem, Carbon $frmDate, Carbon $toDate, &$ESCMode, $rnk = 0, $b2bflag = false)
    {
        $wTimePrice = 0;
        $wDatePrice = 0;
        $getPriceBasic = 0;
 
        $CLCFLAG = (int)$arrPlanItem['clcflag'];
 
        switch ($CLCFLAG) {
            case 2:
                $getPriceBasic = (0 == $rnk) ? $this->getTimePrice($arrPlanItem, $frmDate, $toDate, $b2bflag) : $arrPlanItem[$this->getRankPriceName('price06', $b2bflag)];
                $ESCMode = 1;
                break;
            case 3:
                $getPriceBasic = $this->getDatePrice($arrPlanItem[$this->getRankPriceName('pricec1', $b2bflag)], $frmDate, $toDate);
                $ESCMode = 2;
                break;
            case 4:
                $getPriceBasic = $arrPlanItem[$this->getRankPriceName('pricefx', $b2bflag)];
                $ESCMode = 2;
                break;
            default:
                $wTimePrice = $this->getTimePrice($arrPlanItem, $frmDate, $toDate, $b2bflag);
                $wDatePrice = $this->getDatePrice($arrPlanItem[$this->getRankPriceName('pricec1', $b2bflag)], $frmDate, $toDate);
                if ($wTimePrice < $wDatePrice) {
                    $getPriceBasic = $wTimePrice;
                    $ESCMode = 1;
                } else {
                    $getPriceBasic = $wDatePrice;
                    $ESCMode = 2;
                }
                break;
        }
        return $getPriceBasic;
 
    }
 
    /**
     * Calculate time price by From_date and To_date
     *
     * @param array $arrPrice
     * @param Carbon $frmDate
     * @param Carbon $toDate
     * @param bool $b2bFlag
     * @return float|int|mixed
     */
    private function getTimePrice(array $arrPrice, Carbon $frmDate, Carbon $toDate, $b2bFlag = false)
    {
 
        $getPriceTime = 0;
        $wDiff = round((float)$frmDate->diffInMinutes($toDate) / 60.0);
 
        if ($wDiff <= 3 && $arrPrice[$this->getRankPriceName('price03', $b2bFlag)] != 0) {
            $getPriceTime = $arrPrice[$this->getRankPriceName('price03', $b2bFlag)];
        } else if ($wDiff > 3 && $wDiff <= 6 && $arrPrice[$this->getRankPriceName('price06', $b2bFlag)] != 0) {
            $getPriceTime = $arrPrice[$this->getRankPriceName('price06', $b2bFlag)];
        } else if ($wDiff > 6 && $wDiff <= 12 && $arrPrice[$this->getRankPriceName('price12', $b2bFlag)] != 0) {
            $getPriceTime = $arrPrice[$this->getRankPriceName('price12', $b2bFlag)];
        } else if ($wDiff > 12 && $wDiff <= 24) {
            $getPriceTime = $arrPrice[$this->getRankPriceName('price24', $b2bFlag)];
        } else {
            //	'24時間超過 1日目の24時間料金+以後一日の料金(初日引く)　に余りの時間×1時間超過料金　<　1日目の24時間料金+以後一日基本料金		'24時間超過
            $tmpX = (float)$arrPrice[$this->getRankPriceName('price24', $b2bFlag)] + (((int)($wDiff / 24 - 1) * $arrPrice[$this->getRankPriceName('pricea1', $b2bFlag)]) + ($arrPrice[$this->getRankPriceName('priceex', $b2bFlag)] * ceil($wDiff % 24)));
            $tmpY = (float)$arrPrice[$this->getRankPriceName('price24', $b2bFlag)] + (int)($wDiff / 24) * $arrPrice[$this->getRankPriceName('pricea1', $b2bFlag)];
 
            if ($tmpX < $tmpY) {
                $getPriceTime = $tmpX;
            } else {
                $getPriceTime = $tmpY;
            }
        }
 
        return $getPriceTime;
    }
 
    /**
     * Get Discount Price of One Plan
     *
     * @param array $arrPlanItem
     * @param Carbon $frmDate
     * @param $TYP
     * @param $KBN
     * @param $DSC
     * @return bool
     */
    private function getPriceDiscount(array $arrPlanItem, Carbon $frmDate, &$TYP, &$KBN, &$DSC)
    {
        $result = false;
        $BFRFLAG = (int)$arrPlanItem['bfrflag'];
        $DSCFLAG = (int)$arrPlanItem['dscflag'];
        $DISCDAT = $arrPlanItem['discdat'];
 
        $discount = null;
        if ($DISCDAT != null) {
            $discount = CommonFunctions::splitByTabChar2Arr($DISCDAT, false);
            if (2 == (int)$discount[1]) {
                if (0 == $BFRFLAG) {
                    $todayPlus = Carbon::now()->startOfDay()->addDay((int)$discount[3]);
                    if ($frmDate->gte($todayPlus)) {
                        $result = true;
                    }
                }
//            } else {
//                if (0 == $DSCFLAG) {
//                    $result = true;
//                }
            }
        }
 
        if ($result) {
            $TYP = $discount[1]; //	割引区分
            $KBN = $discount[2]; // 割引内容区分
            switch ($KBN) {
                case '1':
                    $DSC = (int)$discount[4];
                    break;
                case '2':
                    $DSC = (int)$discount[5];
                    break;
                default:
                    $DSC = (int)$discount[6];
                    break;
            }
        } else {
            $TYP = "";
            $KBN = "";
            $DSC = 0;
        }
 
        return $result;
    }
 
    /**
     * Calculate date price by From_date and To_date
     *
     * @param $PRICEC1
     * @param Carbon $frmDate
     * @param Carbon $toDate
     * @return int
     */
    private function getDatePrice($PRICEC1, Carbon $frmDate, Carbon $toDate)
    {
        $frmDate2 = Carbon::create($frmDate->year, $frmDate->month, $frmDate->day);
        $toDate2 = Carbon::create($toDate->year, $toDate->month, $toDate->day);
 
        $wDiff = $frmDate2->diffInDays($toDate2) + 1;
        return ((int)$PRICEC1 * $wDiff);
    }
 
    /**
     * Get number cars in stock
     *
     * @param $brdcode
     * @param $shpcode
     * @param $svhcode
     * @param Carbon $ymd
     * @param Carbon $frmDate
     * @param Carbon $toDate
     * @param $PLNTYPE
     * @return int
     */
    private function getZaiko($brdcode, $shpcode, $svhcode, Carbon $ymd, Carbon $frmDate, Carbon $toDate, $PLNTYPE)
    {
        $result = -99999;
 
        $stockColumns = [
            "Z.BRDCODE", "Z.SHPCODE", "Z.SELCARS",
            "Z.SVHCODE", "Z.RNTDATE"
        ];
 
        $stockQuery = DB::table("M_STOCK Z")->select($stockColumns)
            ->join("M_PLAN P", function ($join) use ($PLNTYPE) {
                $join->on('P.BRDCODE', '=', 'Z.BRDCODE')
                    ->whereColumn('P.SHPCODE', '=', 'Z.SHPCODE')
                    ->whereColumn('P.SVHCODE', '=', 'Z.SVHCODE')
                    ->where('P.DELFLAG', '=', '0')
                    ->where('P.B2BFLAG', '=', $PLNTYPE);
            })
            ->join("M_SHPVEHICLE SVE", function ($join) {
                $join->on('SVE.SVHCODE', '=', 'Z.SVHCODE')
                    ->where('SVE.DELFLAG', '=', '0');
            })
            ->join("M_BRAND BR", function ($join) {
                $join->on('BR.BRDCODE', '=', 'Z.BRDCODE')
                    ->where('BR.DELFLAG', '=', '0');
            })
            ->join("M_SHOP SH", function ($join) {
                $join->on('SH.BRDCODE', '=', 'Z.BRDCODE')
                    ->whereColumn('SH.SHPCODE', '=', 'Z.SHPCODE')
                    ->where('SH.DELFLAG', '=', '0');
            })
            ->whereBetween('Z.RNTDATE', [$frmDate->format('Ymd'), $toDate->format('Ymd')])
            ->where('Z.DELFLAG', '=', '0')
            ->where('Z.BRDCODE', '=', $brdcode)
            ->where('Z.SHPCODE', '=', $shpcode)
            ->where('Z.SVHCODE', '=', $svhcode)
            ->where('Z.RNTDATE', '=', $ymd->format('Ymd'))
            ->groupBy('Z.BRDCODE', 'Z.SHPCODE', 'Z.SELCARS', 'Z.SVHCODE', 'Z.RNTDATE');
 
        $stockData = $stockQuery->get()->toArray();
 
        if (count($stockData) != 0) {
            $elementStock = (array)$stockData[0];
            $result = $elementStock['selcars'];
        }
        return $result;
    }
 
    /**
     * オプション料金計算
     *
     * @param $option
     * @param $arrCond
     * @return int
     */
    private function getOPRIC($option, $arrCond)
    {
        $oprice = 0;
        $ptype = $arrCond["plantype"];
        $rnk = $arrCond["rnk"];
 
        $startDate = Carbon::instance($arrCond['strdate']);
        $endDate = Carbon::instance($arrCond['enddate']);
        switch ($ptype) {
            case 0:
                $oprice = $option[6];
                break;
            case 1:
                $oprice = $option[9];
                break;
            default;
                $oprice = $option[8];
                break;
        }
        switch ($option[3]) {
            case 1:
                break;
            case 2:
                if ($rnk !== 1) {
                    $wDiff = Carbon::create($startDate->year, $startDate->month, $startDate->day)->diffInDays(Carbon::create($endDate->year, $endDate->month, $endDate->day)) + 1;
                } else {
                    $wDiff = 1;
                }
                $oprice = $oprice * $wDiff;
                break;
            default:
                if ($rnk !== 1) {
                    $wDiff = $startDate->diffInHours($endDate);
                    if (($wDiff % 24) > 0) {
                        $wDiff = round(($wDiff / 24), 0, PHP_ROUND_HALF_DOWN) + 1;
                    } else {
                        $wDiff = round(($wDiff / 24), 0, PHP_ROUND_HALF_DOWN);
                    }
                } else {
                    $wDiff = 1;
                }
                $oprice = $oprice * $wDiff;
                break;
        }
        if ($option[4] == 1) {
            if ($oprice > $option[7]) {
                $oprice = $option[7];
            }
        }
        return $oprice;
    }
 
    /**
     * Calculate a price of plans (CopyRecordset)
     *
     * @param array $arrDataPlans
     * @param Carbon $frmDate
     * @param Carbon $toDate
     * @param $PLNTYPE
     * @return array
     */
    private function CalculatePrice($arrDataPlans, Carbon $frmDate, Carbon $toDate, $PLNTYPE, $rnk = 0)
    {
 
        $escmode = 0;
 
        $processDataPlans = [];
 
        foreach ($arrDataPlans as $planItem) {
            $planItem = (array)$planItem;
            $val = 0;
            $wDiff = 0;
            $wZaiko1 = 0;
            $wPrice1 = 0;
            $wPrice2 = 0;
            $wPrice3 = 0;
            $wPrice5 = 0;
            $wBuyPrice = 0;
 
            $planItem['p1'] = 0;
            $planItem['p2'] = 0;
            $planItem['p3'] = 0;
            $planItem['p5'] = 0;
            $planItem['price'] = 0;
            $planItem['menpric'] = 0;
            $planItem['dsckubn'] = '0';
            $planItem['zaiko'] = 0;
            $planItem['dcnkubn'] = '0';
            $planItem['dscpric'] = 0;
            $planItem['buypric'] = 0;
            $b2bflag = $PLNTYPE == 1 ? true : false;
            $wPrice1 = $this->getPriceBasic($planItem, $frmDate, $toDate, $escmode, $rnk, false);
            if ($b2bflag) {
                $wBuyPrice = $this->getPriceBasic($planItem, $frmDate, $toDate, $escmode, $rnk, true);
            }
            $wPrice2 = 0;
            if (1 == $escmode) {
                $val = (int)floor((float)($frmDate->diffInMinutes($toDate) / 1440));
                $wDiff = $val + ((int)($frmDate->diffInMinutes($toDate) % 1440) == 0 ? 0 : 1);
            } else {
                $wDiff = (int)(Carbon::create($frmDate->year, $frmDate->month, $frmDate->day)
                        ->diffInDays(Carbon::create($toDate->year, $toDate->month, $toDate->day))) + 1;
            }
            if ($b2bflag == 1) {
                $planItem['escpric'] = 0;
            }
            if (2 == $planItem['escflag']) {
                $wPrice2 = $planItem['escpric'] * $wDiff;
            } else {
                $wPrice2 = $planItem['escpric'] * $wDiff + $wPrice1;
            }
            if ($rnk === 1) {
                $wPrice2 = $planItem['escpric'];
            }
 
            if (1 == $planItem['fofflag']) {
                $wPrice3 = 0;
            } else {
                $wPrice3 = $planItem['droffprice'];
            }
            $wDSCTY = (int)$planItem["dcacf"];
            $wTYP = '';
            $wKBN = '';
            $wDSC = 0;
 
            $res = $this->getPriceDiscount($planItem, $frmDate, $wTYP, $wKBN, $wDSC);
 
            if ($res) {
                switch ($wKBN) {
                    case '1':
                        $wPrice5 = $wDSC * -1;
                        break;
                    case '2':
                        $wPrice5 = round(($wPrice1) * ($wDSC / 100) * -1);
                        break;
                    default:
                        $wPrice5 = 0;
                        break;
                }
            }
 
            $wZaiko = 0;
 
            $wZaikoDate1 = $frmDate->copy();
            $diffDaysFrmTo = $frmDate->diffInDays($toDate);
            for ($j = 1; $j <= $diffDaysFrmTo + 1; $j++) {
                $ymd = $wZaikoDate1->copy();
                $wZaiko1 = $this->getZaiko($planItem['brdcode'], $planItem['shpcode'], $planItem['svhcode'], $ymd, $frmDate, $toDate, $PLNTYPE);
                if ($wZaiko1 === -99999) {
                    $wZaiko = $wZaiko1;
                    break;
                } else if ($wZaiko1 > 0) {
                    $wZaiko += $wZaiko1;
                } else {
                    $wZaiko = 0;
                    break;
                }
                $wZaikoDate1 = $wZaikoDate1->addDay(1);
            }
 
            if ($wZaiko !== -99999 && $wPrice1 > 0) {
                $planItem['p1'] = $wPrice1;
                $planItem['p2'] = $wPrice2;
                $planItem['p3'] = $wPrice3;
                $planItem['p5'] = $wPrice5;
                $planItem['price'] = $wPrice1 + $wPrice2 + $wPrice3 + $wPrice5;
                $planItem['menpric'] = $wPrice1 + $wPrice5;
                if(is_array($wTYP)){
                    $planItem['dsckubn'] = (count($wTYP) == 0 ? "0" : $wTYP);
                }else{
                    $planItem['dsckubn'] = (strlen($wTYP) == 0 ? "0" : $wTYP);
                }
                $planItem['zaiko'] = $wZaiko;
                $planItem['dcnkubn'] = $wKBN;
                $planItem['dscpric'] = $wDSC;
                $planItem['buypric'] = $wBuyPrice;
                array_push($processDataPlans, $planItem);
            }
            $escmode = 0;
        }
 
        return $processDataPlans;
    }
 
    private function getEquipsString($isSmoking = true)
    {
        $equipQuery = DB::table("M_EQUIPMENT")->select("VEQCODE")
            ->where("DELFLAG", "0");
        if ($isSmoking) {
            $equipQuery->where("VEQCODE", "<>", "0002");
        } else {
            $equipQuery->where("VEQCODE", "<>", "0001");
        }
        $equipRe = $equipQuery->orderBy("VEQCODE")->get();
 
        $strEquip = "";
        foreach ($equipRe as $item) {
            $strEquip .= "'" . $item->veqcode . "',";
        }
        $strEquip = rtrim($strEquip, ',');
        return $strEquip;
    }
 
    /**
     * @param $cmpcode
     * @param $brdcode
     * @param $lang
     * @return array
     * @uses get list cancel policy
     */
    public function getCancelPolicy($cmpcode, $brdcode, $lang)
    {
        $langCmt = [
            'jp' => 'CANCELCOMT_LANG',
            'en' => 'CANCELCOMT_LAN1',
            'tw' => 'CANCELCOMT_LAN2',
            'cn' => 'CANCELCOMT_LAN3',
            'kr' => 'CANCELCOMT_LAN4'
        ];
        $langCancelCmt = [
            'jp' => 'TITLE_LANG',
            'en' => 'TITLE_LAN1',
            'tw' => 'TITLE_LAN2',
            'cn' => 'TITLE_LAN3',
            'kr' => 'TITLE_LAN4'
        ];
        $selectColumns = [
            'CMP.CANCELMAX AS canmax',
            'CMP.CANCELMAXFLAG AS cmaxflg',
            'CMP.' . $langCmt[$lang] . ' AS cancomt',
            'M_CANCELFEES.STARTDATE AS startdate',
            'M_CANCELFEES.STOPDATE AS stopdate',
            \DB::raw('M_CANCELFEES.VALUE AS val'),
            'M_CANCELFEES.' . $langCancelCmt[$lang] . ' AS cmt'
        ];
        $rs = DB::table('M_CANCELFEES')->join('M_COMPANY CMP', function ($join) {
            $join->on('M_CANCELFEES.BRDCODE', '=', 'CMP.BRDCODE');
            $join->whereColumn('M_CANCELFEES.COMCODE', '=', 'CMP.CMPCODE');
        })->select($selectColumns)
            ->where(['CMP.CMPCODE' => $cmpcode, 'CMP.BRDCODE' => $brdcode])->get()->toArray();
 
        if (!$rs) {
            $tmp = [
                'canmax' => '',
                'cmaxflg' => '',
                'cancomt' => '',
                'canlist' => []
            ];
 
            return $tmp;
        }
        $policies = [
            'canmax' => $rs['0']->canmax,
            'cmaxflg' => $rs['0']->cmaxflg,
            'cancomt' => $rs['0']->cancomt
        ];
        for ($i = 0; $i < count($rs); $i++) {
            if (intval($rs[$i]->startdate) != -1 || intval($rs[$i]->stopdate) != -1 || intval($rs[$i]->val) != 0) {
                $policies['canlist'][] = [
                    'start' => intval($rs[$i]->startdate),
                    'stop' => intval($rs[$i]->stopdate),
                    'value' => $rs[$i]->val,
                    'cmt' => $rs[$i]->cmt
                ];
            }
        }
        return $policies;
    }
 
    /**
     * @param $pricName
     * @param bool $b2bFlag
     * @return string
     * @uses get price name b2b and non b2b
     */
    private function getRankPriceName($pricName, $b2bFlag = false)
    {
        $rank = [
            'price03' => 'buypr03',
            'price06' => 'buypr06',
            'price12' => 'buypr12',
            'price24' => 'buypr24',
            'pricea1' => 'buypra1',
            'priceex' => 'buyprex',
            'pricec1' => 'buyprc1',
            'pricefx' => 'buyprfx'
        ];
 
        if (!$b2bFlag) {
            return $pricName;
        } else {
            return $rank[$pricName];
        }
    }
 
    /**
     * Process to get plan by conditions v2
     *
     * @param $arrCond
     * @param $plans
     * @return array
     */
    public function getPlansByConditionV2($arrCond, &$plans)
    {

        \DB::listen(
            function ($sql) {
                foreach ($sql->bindings as $i => $binding) {
                    if ($binding instanceof \DateTime) {
                        $sql->bindings[$i] = $binding->format('\'Y-m-d H:i:s\'');
                    } else {
                        if (is_string($binding)) {
                            $sql->bindings[$i] = "'$binding'";
                        }
                    }
                }
        
                // Insert bindings into query
                $query = str_replace(array('%', '?'), array('%%', '%s'), $sql->sql);
        
                $query = vsprintf($query, $sql->bindings);
                echo date('Y-m-d H:i:s') . ':>>> ' . $query . "<<<";
                // Save the query to file

            }
        );

        $results = [];
        $now = Carbon::now();
        $startDate = Carbon::instance($arrCond['strdate']);
        $endDate = Carbon::instance($arrCond['enddate']);
        $planType = $arrCond['plantype'];
        $currentLang = strtolower($this->getLang());
        $rnk = ((int)$arrCond['rnk'] !== 1) ? 0 : 1;
 
        $planQuery = null;
 
        $req_shoplist_str = "";
        $brshpEquip = "";
        $brshpStock = "";
        $brshpDropOff = "";
        $brshpOption = "";
        if ($rnk === 0) {
            $req_shoplist = $this->getRequiredShopList($arrCond);
            if (count($req_shoplist) > 0) {
                $req_shoplist_str = $this->convertShopListSqlIn($req_shoplist);
                $brshpEquip = " AND (Q_IN.BRDCODE,Q_IN.SHPCODE) IN (" . $req_shoplist_str . ")";
                $brshpStock = " AND (Z_IN.BRDCODE,Z_IN.SHPCODE) IN (" . $req_shoplist_str . ")";
                $brshpDropOff = " AND (NOR.BRDCODE, NOR.SHPCOD1) IN (" . $req_shoplist_str . ")";
                $brshpOption = " ((O_IN.STRDATE<=O_IN.ENDDATE AND " . $startDate->format('md') . " BETWEEN O_IN.STRDATE AND O_IN.ENDDATE) OR (STRDATE>=O_IN.ENDDATE AND (STRDATE<=" . $startDate->format('md') . " OR O_IN.ENDDATE>=" . $startDate->format('md') . "))) AND";
            } else {
                return $planQuery;
            }
        }
 
        $langPlan = [
            'jp' => 'PLNNAME',
            'en' => 'PLNNAM1',
            'tw' => 'PLNNAM2',
            'cn' => 'PLNNAM3',
            'kr' => 'PLNNAM4'
        ];
        $langPLNCOMT = [
            'jp' => 'PLNCOMT',
            'en' => 'PLNCMT1',
            'tw' => 'PLNCMT2',
            'cn' => 'PLNCMT3',
            'kr' => 'PLNCMT4'
        ];
        $langBrand = [
            'jp' => 'BRDNAME',
            'en' => 'BRDNAM1',
            'tw' => 'BRDNAM2',
            'cn' => 'BRDNAM3',
            'kr' => 'BRDNAM4'
        ];
        $langPREF = [
            'jp' => 'APCNAM1',
            'en' => 'LANNAM1',
            'tw' => 'LANNAM2',
            'cn' => 'LANNAM3',
            'kr' => 'LANNAM4'
        ];
        $langAddress = [
            'jp' => '(Z.APCNAM1 || S.CITY || S.ADDR1 || S.ADDR2)',
            'en' => 'S.ADDRES1',
            'tw' => 'S.ADDRES2',
            'cn' => 'S.ADDRES3',
            'kr' => 'S.ADDRES4'
        ];
        $langAccomt = [
            'jp' => 'ACCCOMT',
            'en' => 'ACCOMT1',
            'tw' => 'ACCOMT2',
            'cn' => 'ACCOMT3',
            'kr' => 'ACCOMT4',
        ];
        $langPupCMT = [
            'jp' => 'PUPCOMT',
            'en' => 'SHOCMT1',
            'tw' => 'SHOCMT2',
            'cn' => 'SHOCMT3',
            'kr' => 'SHOCMT4'
        ];
        $langELC = [
            'jp' => 'ECLNAME',
            'en' => 'ECLNAM1',
            'tw' => 'ECLNAM2',
            'cn' => 'ECLNAM3',
            'kr' => 'ECLNAM4'
        ];
        $langVEH = [
            'jp' => 'VEHNAME',
            'en' => 'VEHNAM1',
            'tw' => 'VEHNAM2',
            'cn' => 'VEHNAM3',
            'kr' => 'VEHNAM4'
        ];
        $langVCL = [
            'jp' => 'VCLNAME',
            'en' => 'VCLNAM1',
            'tw' => 'VCLNAM2',
            'cn' => 'VCLNAM3',
            'kr' => 'VCLNAM4'
        ];
        $langSHP = [
            'jp' => 'SHPNAME',
            'en' => 'SHPNAM1',
            'tw' => 'SHPNAM2',
            'cn' => 'SHPNAM3',
            'kr' => 'SHPNAM4'
        ];
        $columns = [
            "P.PLNCODE",
            "P." . $langPlan[$currentLang] . " AS PLNNAME",
            "P.PLNNAME AS PLNNAMEORIGIN",
            "P." . $langPLNCOMT[$currentLang] . " AS PLNCOMT",
            "P.OPTFLAG",
            "P.GOFFLAG",
            "P.ESCFLAG",
            "P.FOPFLAG",
            "P.CLCFLAG", "P.ESCPRIC", "P.FOFFLAG",
            "P.BFRFLAG", "P.DSCFLAG", "P.BRDCODE", "P.SHPCODE", "P.PLNIMG1", "P.UPDDATE",
            "B." . $langBrand[$currentLang] . " AS BRDNAME",
            "B.BRDNAME AS BRDNAMEORIGIN",
            "S.CMPCODE",
            "S." . $langSHP[$currentLang] . " AS SHPNAME",
            "S.SHPNAME AS SHPNAMEORIGIN",
            "S.STOKKBN", "S.PRFCODE", "S.GOFFLAG AS SHPGOFFLAG",
            "Z." . $langPREF[$currentLang] . " AS PRFNAME",
            "S.CITY",
            DB::raw($langAddress[$currentLang] . ' AS addr1'),
            "S.ADDR2",
            "S." . $langAccomt[$currentLang] . " AS ACCCOMT",
            "S.ASCCOD0",
            "S.ASCCOD1",
            "S.ASCCOD2",
            "S.ASCCOD3",
            "S.ASCCOD4",
            DB::raw("(TRIM(S.TEL1) || '-' || TRIM(S.TEL2) || '-' || TRIM(S.TEL3)) AS SHOPTEL"),
            "S.PUPFLAG",
            "S." . $langPupCMT[$currentLang] . " AS PUPCOMT",
            "C.CMPNAME",
            "C.CMPIMAG",
            "C.HIDCOMT",
            "E.ECLCODE",
            "E." . $langELC[$currentLang] . " AS ECLNAME",
            "E.ECLNAME AS ECLNAMEORIGIN",
            "W.SVHCODE",
            "W.CLSFLAG",
            "V.VEHCODE",
            "V." . $langVEH[$currentLang] . " AS VEHNAME",
            "V.VEHNAME AS VEHNAMEORIGIN",
            "V.IMGFIL1",
            "V.PSNCAPA",
            "A.VCLCODE",
            "A." . $langVCL[$currentLang] . " AS VCLNAME",
            "A.VCLNAME AS VCLNAMEORIGIN",
            DB::raw("(CASE WHEN LENGTH(S.ASCCOD0) <> 0 THEN (SELECT SECCODE FROM M_LANDMARK WHERE ASCCODE=S.ASCCOD0 AND DELFLAG='0') ELSE '' END) AS SECCOD1"),
            DB::raw("(CASE WHEN LENGTH(S.ASCCOD1) <> 0 THEN (SELECT SECCODE FROM M_LANDMARK WHERE ASCCODE=S.ASCCOD1 AND DELFLAG='0') ELSE '' END) AS SECCOD2"),
            DB::raw("(CASE WHEN LENGTH(S.ASCCOD2) <> 0 THEN (SELECT SECCODE FROM M_LANDMARK WHERE ASCCODE=S.ASCCOD2 AND DELFLAG='0') ELSE '' END) AS SECCOD3"),
            DB::raw("(CASE WHEN LENGTH(S.ASCCOD3) <> 0 THEN (SELECT SECCODE FROM M_LANDMARK WHERE ASCCODE=S.ASCCOD3 AND DELFLAG='0') ELSE '' END) AS SECCOD4"),
            DB::raw("(CASE WHEN LENGTH(S.ASCCOD4) <> 0 THEN (SELECT SECCODE FROM M_LANDMARK WHERE ASCCODE=S.ASCCOD4 AND DELFLAG='0') ELSE '' END) AS SECCOD5"),
            "K.BPCCODE", "K.BPRCODE",
            "K.PRICE03", "K.PRICE06", "K.PRICE12", "K.PRICE24", "K.PRICEA1", "K.PRICEEX", "K.PRICEC1", "K.PRICEFX",
            "K.BUYPR03", "K.BUYPR06", "K.BUYPR12", "K.BUYPR24", "K.BUYPRA1", "K.BUYPREX", "K.BUYPRC1", "K.BUYPRFX",
            "Q.EQUIPDT", "O.OPTNDAT", "D.DISCDAT", "Z1.SELCARS", "NOR.DROFFPRICE","D.DCACF",
            "P.TRMCODE",
            "P.ISPAYCOD","P.ISPAYCRE","P.ISPAYARV",
            "C.CREDITFLG",
        ];
 
        $langVeq = [
            'jp' => 'VEQNAME',
            'en' => 'VEQNAM1',
            'tw' => 'VEQNAM2',
            'cn' => 'VEQNAM3',
            'kr' => 'VEQNAM4'
        ];
        $tmpEquipment = DB::raw("(SELECT Q_IN.BRDCODE, Q_IN.SHPCODE, Q_IN.SVHCODE,"
            . " TO_CHAR(WM_CONCAT(Q_IN.VEQCODE || CHR(9) || E_IN." . $langVeq[$currentLang] . " || CHR(9) || Q_IN.VEQGRUP || CHR(9) || E_IN.ICNFILE || CHR(9) || E_IN.VEQNAME)) AS EQUIPDT"
            . " FROM M_SHPEQUIPMENT Q_IN INNER JOIN M_EQUIPMENT E_IN"
            . " ON E_IN.VEQCODE=Q_IN.VEQCODE AND E_IN.DELFLAG='0'"
            . " WHERE Q_IN.DELFLAG='0'"
            . $brshpEquip
            . " GROUP BY Q_IN.BRDCODE,Q_IN.SHPCODE,Q_IN.SVHCODE) Q"
        );
 
        $langSVO = [
            'jp' => 'SVONAME',
            'en' => 'SVONAM1',
            'tw' => 'SVONAM2',
            'cn' => 'SVONAM3',
            'kr' => 'SVONAM4'
        ];
        $tmpShopOpt = DB::raw("(SELECT O_IN.BRDCODE, O_IN.SHPCODE,"
            . " TO_CHAR(WM_CONCAT(O_IN.SVOCODE || CHR(9) || O_IN.VOPCODE || CHR(9) || O_IN." . $langSVO[$currentLang] . " || CHR(9) || O_IN.UPRFLAG || CHR(9) || O_IN.UBOFLAG || CHR(9) || O_IN.VOPFLAG || CHR(9) || TO_CHAR(O_IN.PRICE) || CHR(9) || TO_CHAR(O_IN.UBOPRIC) || CHR(9) || TO_CHAR(O_IN.DPOPRIC)|| CHR(9) || TO_CHAR(O_IN.B2OPRIC))) AS OPTNDAT"
            . " FROM M_SHPOPTION O_IN"
            . " WHERE"
            . $brshpOption
            . " O_IN.DELFLAG='0'"
            . " GROUP BY O_IN.BRDCODE,O_IN.SHPCODE) O"
        );
 
        $tmpDiscount = DB::raw("(SELECT D_IN.BRDCODE, D_IN.SHPCODE, D_IN.DSCCODE,D_IN.DCACF,"
            //Using for oracle 11g or higher
            //. " TO_CHAR(LISTAGG(TO_CHAR(D_IN.STRDATE) || CHR(9) || D_IN.DSCKUBN || CHR(9) || D_IN.DCNKUBN || CHR(9) || TO_CHAR(D_IN.DAYSBFR) || CHR(9) || TO_CHAR(D_IN.DCNPRIC) || CHR(9) || TO_CHAR(D_IN.DCNPERC) || CHR(9) || D_IN.DSCCOMT, ',') WITHIN GROUP (ORDER BY D_IN.DSCCODE DESC)) AS DISCDAT"
            //Using for oracle 10g
            . " TO_CHAR(WM_CONCAT(TO_CHAR(D_IN.STRDATE) || CHR(9) || D_IN.DSCKUBN || CHR(9) || D_IN.DCNKUBN || CHR(9) || TO_CHAR(D_IN.DAYSBFR) || CHR(9) || TO_CHAR(D_IN.DCNPRIC) || CHR(9) || TO_CHAR(D_IN.DCNPERC) || CHR(9) || D_IN.DSCCOMT)) AS DISCDAT"
            . " FROM M_DISCOUNT D_IN"
            . " WHERE ((D_IN.DATEKBN='1' AND " . $now->format('Ymd') . " BETWEEN D_IN.STRDATE AND D_IN.ENDDATE) OR (D_IN.DATEKBN='2' AND " . $startDate->format('Ymd') . " BETWEEN D_IN.STRDATE AND D_IN.ENDDATE))"
            . " AND D_IN.DELFLAG='0'"
            . " GROUP BY D_IN.BRDCODE,D_IN.SHPCODE,D_IN.DSCCODE,D_IN.DCACF"
            . " ORDER BY D_IN.DSCCODE DESC) D"
        );
 
        $tmpStock = DB::raw("(SELECT Z_IN.BRDCODE, Z_IN.SHPCODE, Z_IN.SVHCODE, MIN(Z_IN.SELCARS) AS SELCARS"
            . " FROM M_STOCK Z_IN"
            . " WHERE Z_IN.RNTDATE BETWEEN " . $startDate->format('Ymd') . " AND " . $endDate->format('Ymd') . " AND Z_IN.DELFLAG='0'"
            . $brshpStock
            . " GROUP BY Z_IN.BRDCODE,Z_IN.SHPCODE,Z_IN.SVHCODE) Z1"
        );
 
        $dropOffPriceCol = ($planType == 2) ? "DPNPRIC" : "PRICE";
 
        $tmpDropOff = DB::raw("(SELECT NOR.BRDCODE, NOR.SHPCOD1, MIN(NOR." . $dropOffPriceCol . ") AS DROFFPRICE"
            . " FROM M_NORISUTE NOR"
            . " WHERE NOR.DELFLAG ='0'"
            . $brshpDropOff
            . " GROUP BY NOR.BRDCODE, NOR.SHPCOD1) NOR");
 
        // P
        $planQuery = DB::table('M_PLAN P')->distinct()->select($columns)
            ->join('M_BRAND B', function ($join) {
                $join->on('B.BRDCODE', '=', 'P.BRDCODE')
                    ->where('B.DELFLAG', Constants::DB_DELETE_FLAG_TRUE);
            })
            // S M_SHOP
            ->join('M_SHOP S', function ($join) use ($arrCond) {
                $join->on('S.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('S.SHPCODE', '=', 'P.SHPCODE');
                if ($arrCond['plantype'] === 0) {
                    $join->whereRaw("S.SYAGFLG='1'");
                }
                /** b2b */
                if ($arrCond['plantype'] == 1) {
                    $join->where('S.B2BFLAG', '=', 1);
                }
                $join->whereRaw("S.SYUSEDT<='" . Carbon::now()->format('Ymd') . "' AND S.DELFLAG = '0'");
            })
            // C M_COMPANY
            ->join('M_COMPANY C', function ($join) {
                $join->on('C.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('C.CMPCODE', '=', 'S.CMPCODE')
                    ->whereRaw("C.DELFLAG='0'");
            })
            // E M_EXHCLASS
            ->join('M_EXHCLASS E', function ($join) {
                $join->on('E.ECLCODE', '=', 'P.ECLCODE')
                    ->whereRaw("E.DELFLAG='0'");
            })
            // Z M_APPLICATION
            ->join('M_APPLICATION Z', function ($join) {
                $join->on('S.PRFCODE', '=', 'Z.APCKEY1')
                    ->whereRaw("Z.DELFLAG='0' AND Z.APCCODE='PREF' AND Z.APCKEY2 = '0'");
            })
            // W M_SHPVEHICLE
            ->join('M_SHPVEHICLE W', function ($join) {
                $join->on('W.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('W.SHPCODE', '=', 'P.SHPCODE')
                    ->whereColumn('W.SVHCODE', '=', 'P.SVHCODE')
                    ->whereRaw("W.DELFLAG='0'");
            })
            // V M_VEHICLE
            ->join('M_VEHICLE V', function ($join) {
                $join->on('V.VEHCODE', '=', 'W.VEHCODE')
                    ->whereRaw("V.DELFLAG='0'");
            })
            // A M_VEHCLASS
            ->join('M_VEHCLASS A', function ($join) {
                $join->on('A.VCLCODE', '=', 'V.VCLCODE')
                    ->whereColumn('A.VCLCODE', '=', 'P.VCLCODE')
                    ->whereRaw("A.DELFLAG='0'");
            })
            // R M_PLANPRICE
            ->join('M_PLANPRICE R', function ($join) use ($startDate) {
                $join->on('R.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('R.SHPCODE', '=', 'P.SHPCODE')
                    ->whereColumn('R.PLNCODE', '=', 'P.PLNCODE')
                    ->where('R.RNTYYMM', '=', $startDate->format('Ym'))
                    ->whereRaw("R.DELFLAG='0'");
            })
            // K M_PRICERANK
            ->join('M_PRICERANK K', function ($join) use ($startDate) {
                $join->on('K.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('K.SHPCODE', '=', 'P.SHPCODE')
                    ->whereColumn('K.BPCCODE', '=', 'R.BPCCODE')
                    ->whereColumn('K.BPRCODE', '=', 'R.BPRNK' . $startDate->format('d'))
                    ->whereRaw("K.DELFLAG='0'");
            })
            // Q M_SHPEQUIPMENT
            ->leftJoin($tmpEquipment, function ($join) {
                $join->on('Q.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('Q.SHPCODE', '=', 'P.SHPCODE')
                    ->whereColumn('Q.SVHCODE', '=', 'P.SVHCODE');
            })
            // O M_SHPOPTION
            ->leftJoin($tmpShopOpt, function ($join) {
                $join->on('O.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('O.SHPCODE', '=', 'P.SHPCODE')
                    ->where('P.OPTFLAG', '=', Constants::DB_DELETE_FLAG_TRUE);
            })
            // D M_DISCOUNT
            ->leftJoin($tmpDiscount, function ($join) {
                $join->on('D.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('D.SHPCODE', '=', 'P.SHPCODE')
                    ->whereColumn('D.DSCCODE', '=', 'P.DSCCODE');
            })
            // Z1 M_STOCK
            ->join($tmpStock, function ($join) use ($arrCond) {
                $instock = $arrCond['instock'];
                $stknum = $arrCond['stknum'];
                $plantype = $arrCond['plantype'];
                $join->on('Z1.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('Z1.SHPCODE', '=', 'P.SHPCODE')
                    ->whereColumn('Z1.SVHCODE', '=', 'P.SVHCODE')
                    ->where(function ($j) use ($instock, $stknum, $plantype) {
                        if ($plantype == 1) {
                            $j->where('Z1.SELCARS', '>=', $stknum);
                        } else {
                            if ($instock == 1) {
                                $j->where('Z1.SELCARS', '>=', $stknum);
                            } else {
                                $j->whereRaw("(S.STOKKBN='0' AND Z1.SELCARS>0) OR (S.STOKKBN='1' AND Z1.SELCARS>=0)");
                            }
                        }
                    });
            })
            // NOR M_NORISUTE
            ->leftJoin($tmpDropOff, function ($join) {
                $join->on('NOR.BRDCODE', '=', 'P.BRDCODE')
                    ->whereColumn('NOR.SHPCOD1', '=', 'P.SHPCODE');
            })
            //
            ->where(function ($query) use ($arrCond, $startDate, $endDate, $req_shoplist_str, $currentLang, $langPlan) {
                $query->where('P.DELFLAG', Constants::DB_DELETE_FLAG_TRUE);
 
                if ((int)$arrCond['rnk'] === 1) {
                    $query->where(function ($qu) use ($startDate, $endDate) {
                        $qu->where(function ($q) use ($startDate) {
                            $q->where('P.STRDATE', '<=', $startDate->format('Ymd'))
                                ->where('P.ENDDATE', '>=', $startDate->format('Ymd'));
                        });
                        $qu->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('P.STRDATE', '>=', $startDate->format('Ymd'))
                                ->where('P.ENDDATE', '<=', $endDate->format('Ymd'));
                        });
                        $qu->orWhere(function ($q) use ($endDate) {
                            $q->where('P.STRDATE', '<=', $endDate->format('Ymd'))
                                ->where('P.ENDDATE', '>=', $endDate->format('Ymd'));
                        });
                    });
                    $query->where('P.CLCFLAG', '=', 2);
                } else {
                    //プラン有効範囲
                    $query->where('P.STRDATE', '<=', $startDate->format('Ymd'))
                        ->where('P.ENDDATE', '>=', $endDate->format('Ymd'));
                }
 
                if ($currentLang != 'jp' && $arrCond['plantype'] != 1) {
                    $query->where('S.B2CFLAG', '=', 1);
                }
 
                //免責込
                if ($arrCond['esc'] !== "" && $arrCond['esc'] !== 0) {
                    $query->where('P.ESCFLAG', '=', $arrCond['esc']);
                }
                // B2B
                if ($arrCond['plantype'] == 1) {
                    $query->whereNotNull('P.' . $langPlan[$currentLang]);
                }
 
                if ((int)$arrCond['rnk'] !== 1) {
                    //プラン区分
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $wDiff = $startDate->diffInDays($endDate) + 1;
                        $q->whereRaw("(P.PLNKUBN='1')");
                        if ($wDiff <= 7) {
                            $q->orWhere([
                                [DB::raw('TO_NUMBER(P.PLNKUBN)'), '=', '2'],
                                [DB::raw('TO_NUMBER(P.WEPSTRW)'), '=', $startDate->dayOfWeek + 1],
                                [DB::raw('TO_NUMBER(P.WEPSTRT)'), '<=', $startDate->format('Hi')],
                                [DB::raw('TO_NUMBER(P.WEPENDW)'), '=', $endDate->dayOfWeek + 1],
                                [DB::raw('TO_NUMBER(P.WEPENDT)'), '>=', $endDate->format('Hi')],
                            ]);
                        }
                        $wDiff2 = Carbon::create($startDate->year, $startDate->month, $startDate->day)
                                ->diffInDays(Carbon::create($endDate->year, $endDate->month, $endDate->day)) + 1;
                        $q->orWhere([
                            [DB::raw('TO_NUMBER(P.PLNKUBN)'), '=', '3'],
                            [DB::raw('TO_NUMBER(P.LGPDAYS)'), '<=', $wDiff2],
                            [DB::raw('TO_NUMBER(P.LGPDAYE)'), '>=', $wDiff2],
                        ]);
                    });
                }
 
                // 車両クラス
                if (count($arrCond['vclcode']) > 0) {
                    $arrVclCode = $arrCond['vclcode'];
                    $query->where(function ($q) use ($arrVclCode) {
                        $is1stRun = true;
                        foreach ($arrVclCode as $vclCode) {
                            if ($is1stRun) {
                                $q->where('P.VCLCODE', '=', $vclCode);
                                $is1stRun = false;
                            } else {
                                $q->orWhere('P.VCLCODE', '=', $vclCode);
                            }
                        }
                    });
                    unset($arrVclCode);
                }
 
                // 排気量クラス
                if (count($arrCond['eclcode']) > 0) {
                    $arrEclCode = $arrCond['eclcode'];
                    $query->where(function ($q) use ($arrEclCode) {
                        $is1stRun = true;
                        foreach ($arrEclCode as $eclCode) {
                            if ($is1stRun) {
                                $q->where('P.ECLCODE', '=', $eclCode);
                                $is1stRun = false;
                            } else {
                                $q->orWhere('P.ECLCODE', '=', $eclCode);
                            }
                        }
                    });
                    unset($arrEclCode);
                }
 
                if ($arrCond['equip'] === 0 || $arrCond['equip'] === 1) {
                    $smokeVEQCODE = (int)$arrCond['equip'] == 0 ? "0001" : "0002";
                    $query->whereRaw("(SELECT COUNT(*) FROM M_SHPEQUIPMENT E2"
                        . " WHERE E2.BRDCODE=P.BRDCODE AND E2.SHPCODE=P.SHPCODE"
                        . " AND E2.SVHCODE=W.SVHCODE AND E2.VEQCODE='" . $smokeVEQCODE . "' ) > 0");
                }
 
                if ($req_shoplist_str !== "") {
                    $query->whereRaw('(P.BRDCODE,P.SHPCODE) IN (' . $req_shoplist_str . ')');
                }
 
                // キャンペーンコード
                if ($arrCond['camcode'] !== "") {
                    $query->where("P.CAMCODE", "=", str_pad($arrCond['camcode'], 4));
                }
 
                // プランコード
                if ($arrCond['plncode'] !== "") {
                    $query->where("P.PLNCODE", "=", $arrCond['plncode']);
                }
 
                $query->where('P.B2BFLAG', '=', $arrCond['plantype']);
                if ($arrCond['plantype'] == 0 && $arrCond['langflag'] == 0) {
                    $query->where('P.LANFLG0', '=', 1);
                    // 多言語時、海外掲載用プラン抽出
                } elseif ($arrCond['plantype'] != 1 && $arrCond['langflag'] != 0) {
                    $query->where('P.LANFLG1', '=', 1);
                }
 
                if ($arrCond['dropoff'] === 0 || $arrCond['dropoff'] === 1) {
                    $query->where('P.GOFFLAG', '=', $arrCond['dropoff']);
                }
 
                 // 送迎
                if (!empty($arrCond['pupflag']) && (int)$arrCond['pupflag'] != 0) {
                    $query->where("S.PUPFLAG", "=", 1);
                }

                // creflag
                if (isset($arrCond['creflag']) && (int)$arrCond['creflag'] == 0) {
                    $query->where("P.ISPAYCOD", "=", 1);
                }
            });

            
        if ($arrCond['sort'] === 3) {
            $planQuery->orderBy('P.UPDDATE', 'DESC');
            
        }
        $plansListArr = $planQuery->get()->toArray();
        //////////////dd(DB::getQueryLog());
        $plans = collect($this->CalculatePrice($plansListArr, $startDate, $endDate, $arrCond['plantype'], $arrCond['rnk']));
        $planCollections = $plans;
        if ($arrCond['sort'] === 2) {
            $planCollections = $plans->sortByDesc('menpric');
        }
        if ($arrCond['sort'] === 1 || ($arrCond['sort'] !== 2 && $arrCond['sort'] !== 3)) {
            $planCollections = $plans->sortBy('menpric');
        }
        // option 禁煙 ,カーナビ,ETC車載器..4WD
        if(!empty($arrCond["optionsEquip"]) || !empty($arrCond["isStudless"])){
            $andCond = !empty($arrCond["optionsEquip"]) && !empty($arrCond["isStudless"]);
            $planCltions = collect([]);
            $condEquipdts = $arrCond["optionsEquip"];
            foreach ($planCollections as $planItem) {
                $optionEquipdt = [];
                $optionsEquip = false;
                // option 禁煙 ,カーナビ,ETC車載器..4WD
                if (!empty($arrCond['optionsEquip'])){
                    $arrStrEquipdt = CommonFunctions::splitByComma2Arr($planItem['equipdt']);
                    if (count($arrStrEquipdt) > 0) {
                        foreach ($arrStrEquipdt as $equipdtItem) {
                            $tmpEqItem = CommonFunctions::splitByTabChar2Arr($equipdtItem, false);
                            array_push($optionEquipdt, $tmpEqItem[0]);
                        }
                    }
                    $optionsEquip = 0 == count(array_diff($condEquipdts, $optionEquipdt));
                }
                
                // option スタッドレス
                $optionInfo = false;
                if (!empty($arrCond['isStudless'])){
                    $arrStrOptn = CommonFunctions::splitByComma2Arr($planItem['optndat']);
                    if (count($arrStrOptn) > 0) {
                        foreach ($arrStrOptn as $optnItem) {
                            $tmpOpItem = CommonFunctions::splitByTabChar2Arr($optnItem, false);
                            if (isset($tmpOpItem[1]) && $tmpOpItem[1] == "0004") {
                                $optionInfo = true;
                            }
                        }
                    }
                } 
                if($andCond){
                    if($optionInfo === true && $optionsEquip === true){
                        $planCltions->push($planItem);
                    }
                } else {
                    if($optionInfo === true || $optionsEquip === true){
                        $planCltions->push($planItem);
                    }
                }
            }

            $planCollections =  $planCltions;
            $plans =  $planCltions;
        }
        $planChunk = $planCollections->forPage($arrCond['page'], $arrCond['pagecnt']);
        $results = $planChunk->all();
        return $results;
    }
 
    public static function getB2CTermOption(Carbon $startDate, Carbon $endDate, $brdCode, $shpCode, $termCode = '', $planType, $langFlag = 0)
    {
        $termOption = [];
       
        $startOfPickupDate = $startDate->startOfDay();
        $startOfReturnDate = $endDate->startOfDay();
        $dateDiff = $startOfPickupDate->diffInDays($startOfReturnDate) + 1;
        
        if (strlen($termCode) > 0 && $planType == 0 && $langFlag != 0 && (0 < $dateDiff || $dateDiff < 15)){
            
            $priceField = ($dateDiff < 10) ? "TRMPRI" : "TRMPR";
            $priceField = $priceField . $dateDiff;
 
            $selectCols = [
                'TRMCODE as trmcode',
                'TRMNAM' . $langFlag . ' as trmname',
                $priceField . ' as trmprice'
            ];
 
            $termOpts = TermOption::where('M_TERMOPTION.DELFLAG', '=', '0')
                ->where('M_TERMOPTION.BRDCODE', '=', $brdCode)
                ->where('M_TERMOPTION.SHPCODE', '=', $shpCode)
                ->where('M_TERMOPTION.TRMCODE', '=', $termCode)
                ->select($selectCols);
 
            $termOption = $termOpts->get()->toArray();
        }
 
        return $termOption;
    }
 
}
