<?php
require 'includes/lead-score.php';
/* Reason-text regression suite.
   The grade was always right; the EXPLANATION was inferred rather than
   observed. Each case asserts the reason names every failing gate and names
   no gate that actually passed. */
$base = ['client_type'=>'epc_contractor','service'=>'owners_engineer','project_scale'=>'500kw_2mw',
         'project_stage'=>'procurement','decision_horizon'=>'within_3_months','authority'=>'decision_maker',
         'company'=>'Acme Power Ltd','email'=>'e@acme.ng',
         'message'=>'Procuring a 1.5MW mini-grid in Kaduna, need independent design validation before bid award.'];

$cases = [
 ['LIVE LEAD 36: authority+horizon ok, stage concept',
   ['project_stage'=>'concept'], 'mql', ['stage'], ['timeline','authority']],
 ['authority ok, horizon missing, stage ok',
   ['decision_horizon'=>'beyond_12_months'], 'mql', ['timeline'], ['authority','stage']],
 ['horizon ok, authority missing, stage ok',
   ['authority'=>'influencer'], 'mql', ['authority'], ['timeline','stage']],
 ['authority ok, horizon missing, stage missing',
   ['decision_horizon'=>'unsure','project_stage'=>'concept'], 'mql', ['timeline','stage'], ['authority']],
 ['all gates pass but score 60-74 (no gap to report)',
   ['client_type'=>'other','project_scale'=>'50kw_500kw','service'=>'multiple','message'=>'Need help'],
   'mql', ['below the 75'], ['authority','timeline','not yet at a real stage']],
];

$fail=0;
foreach ($cases as [$name,$ov,$expGrade,$mustSay,$mustNotSay]) {
    $r = scoreLead(array_merge($base,$ov));
    $reason = strtolower($r['reason']);
    $errs=[];
    if ($r['grade']!==$expGrade) $errs[]="grade {$r['grade']} != $expGrade";
    foreach ($mustSay as $s)    if (strpos($reason,strtolower($s))===false) $errs[]="missing '$s'";
    foreach ($mustNotSay as $s) if (strpos($reason,strtolower($s))!==false) $errs[]="wrongly claims '$s'";
    if ($errs) $fail++;
    printf("%s %-52s [%s score %d]\n    %s\n", $errs?'FAIL':'ok  ', substr($name,0,52), $r['grade'], $r['score'], $r['reason']);
    foreach ($errs as $e) echo "      -> $e\n";
}
echo "\n", $fail===0 ? "REASON TEXT: ALL ".count($cases)." CASES PASSED\n" : "$fail FAILURE(S)\n";
exit($fail===0?0:1);
