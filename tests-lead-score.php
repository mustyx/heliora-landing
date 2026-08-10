<?php
require 'includes/lead-score.php';

$GOOD_BRIEF = 'We are procuring a 1.5MW interconnected mini-grid in Kaduna and need '
            . 'independent design validation plus protection coordination review before bid award.';

function lead(array $o = []): array {
    return array_merge([
        'client_type'=>'epc_contractor','service'=>'owners_engineer','project_scale'=>'500kw_2mw',
        'project_stage'=>'procurement','decision_horizon'=>'within_3_months','authority'=>'decision_maker',
        'company'=>'Acme Power Ltd','email'=>'e@acme.ng','message'=>$GLOBALS['GOOD_BRIEF'],
    ], $o);
}

$cases = [
  ['THE BUG: type+scale+service only, no authority/horizon/stage',
     ['project_stage'=>'exploring','decision_horizon'=>'beyond_12_months','authority'=>'gathering_info'], 'nurture'],
  ['THE BUG variant: gathering info, no timeline, thin brief',
     ['project_stage'=>'concept','decision_horizon'=>'unsure','authority'=>'gathering_info','message'=>'short'], 'nurture'],
  ['Full SQL: authority + horizon + real stage + 75+', [], 'sql'],
  ['SQL boundary: mandated adviser, within 6 months',
     ['authority'=>'mandated_adviser','decision_horizon'=>'within_6_months'], 'sql'],
  ['Technical evaluator counts as authority', ['authority'=>'technical_evaluator'], 'sql'],
  ['Influencer does NOT count as authority', ['authority'=>'influencer'], 'mql'],
  ['MQL: has horizon, lacks authority', ['authority'=>'gathering_info'], 'mql'],
  ['MQL: has authority, lacks horizon', ['decision_horizon'=>'beyond_12_months'], 'mql'],
  ['Nurture: high score but NEITHER authority nor horizon',
     ['authority'=>'influencer','decision_horizon'=>'unsure'], 'nurture'],
  ['Nurture: no company means not contactable', ['company'=>''], 'nurture'],
  ['Unscored: organic, nothing asked',
     ['project_stage'=>'','decision_horizon'=>'','authority'=>''], 'unscored'],
  ['Disqualify: recruitment', ['message'=>'I am seeking a job, please find my CV attached for a graduate trainee role'], 'disqualify'],
  ['Disqualify: vendor pitch', ['message'=>'We manufacture solar panels and want to become a supplier, here is our price list'], 'disqualify'],
  ['Disqualify: household', ['company'=>'-','message'=>'I want solar for my 3 bedroom house, domestic use only please advise'], 'disqualify'],
  ['NOT disqualified: mentions home office but commercial',
     ['message'=>'Our company factory in Ogun needs 800kW; I also run a home office but this is for the plant'], 'sql'],
  ['Under 50kW vs large service -> gated to nurture', ['project_scale'=>'under_50kw'], 'nurture'],
  ['Undecided scale: 0 pts but still SQL at 80', ['project_scale'=>'undecided'], 'sql'],
  ['50-500kW earns 15 not 20', ['project_scale'=>'50kw_500kw'], 'sql'],
  ['Client type other: 0 pts but still SQL at 80', ['client_type'=>'other'], 'sql'],
  ['Service "multiple" earns partial', ['service'=>'multiple'], 'sql'],
  ['FIX: SQL now requires org+contact (no company)', ['company'=>''], 'nurture'],
  ['FIX: SQL now requires org+contact (no email)', ['email'=>''], 'nurture'],
  ['Under 50kW IS in scope for SHS', ['project_scale'=>'under_50kw','service'=>'shs_design'], 'sql'],
  ['Under 50kW tolerated when service unsure (70 = MQL)', ['project_scale'=>'under_50kw','service'=>'multiple'], 'mql'],
];

$fail = 0;
printf("%-58s %-11s %-11s %5s\n", 'CASE', 'EXPECTED', 'GOT', 'SCORE');
echo str_repeat('-', 92), "\n";
foreach ($cases as [$name, $ov, $expect]) {
    $r = scoreLead(lead($ov));
    $ok = $r['grade'] === $expect;
    if (!$ok) $fail++;
    printf("%-58s %-11s %-11s %5d %s\n", substr($name,0,58), $expect, $r['grade'], $r['score'], $ok?'':' <<< FAIL');
}

echo "\n=== The bug, explained ===\n";
$r = scoreLead(lead(['project_stage'=>'exploring','decision_horizon'=>'beyond_12_months','authority'=>'gathering_info']));
foreach ($r['components'] as $k=>$v) printf("  %-14s %2d\n", $k, $v);
echo "  sum = {$r['score']}\n";
echo "  OLD (sum only): " . ($r['score']>=75?'SQL':($r['score']>=60?'MQL':'nurture')) . "\n";
echo "  NEW (gated):    {$r['grade']}\n  reason: {$r['reason']}\n";

echo "\n=== Lead_Status mapping vs live Zoho picklist ===\n";
$valid = ['-None-','Attempted to Contact','Contact in Future','Contacted','Junk Lead',
          'Lost Lead','Not Contacted','Pre-Qualified','Not Qualified'];
foreach (['sql','mql','nurture','disqualify','unscored'] as $g) {
    $s = mapGradeToLeadStatus($g); $ok = in_array($s,$valid,true); if(!$ok) $fail++;
    printf("  %-11s -> %-20s %s\n", $g, $s, $ok?'valid':'INVALID <<<');
}
echo "\n", $fail===0 ? "ALL ".count($cases)." CASES + 5 MAPPINGS PASSED\n" : "$fail FAILURE(S)\n";
exit($fail===0?0:1);
