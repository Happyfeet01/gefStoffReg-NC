<?php
declare(strict_types=1);
require __DIR__ . '/../lib/Service/ResearchResponse.php';
use OCA\Gefahrstoffkataster\Service\ResearchResponse;
function check(bool $ok): void { if (!$ok) throw new RuntimeException('Regression failed'); }
function rejected(array $response, string $provider): void {
    try { ResearchResponse::candidates($response, $provider); }
    catch (UnexpectedValueException $e) { return; }
    throw new RuntimeException('Invalid response accepted');
}
$json = '{"candidates":[{"name":"Test","source_url":"https://example.org/product"}]}';
$openai = ['status'=>'completed','output'=>[
    ['type'=>'reasoning'],
    ['type'=>'message','content'=>[['type'=>'output_text','text'=>$json]]],
]];
check(ResearchResponse::candidates($openai, 'openai')[0]['name'] === 'Test');
$mistral = ['choices'=>[['finish_reason'=>'stop','message'=>['content'=>$json]]]];
check(ResearchResponse::candidates($mistral, 'mistral')[0]['name'] === 'Test');
$mistral['choices'][0]['message']['content'] = [['type'=>'text','text'=>$json]];
check(ResearchResponse::candidates($mistral, 'mistral')[0]['name'] === 'Test');
$mistral['choices'][0]['finish_reason'] = 'length';
rejected($mistral, 'mistral');
rejected(['status'=>'incomplete','output'=>[]], 'openai');
rejected(['status'=>'completed','output'=>[]], 'openai');
rejected(['status'=>'failed'], 'openai');
$openai['output'][1]['content'] = [['type'=>'refusal']];
rejected($openai, 'openai');
foreach (['{','{"candidates":"wrong"}','{"candidates":[{"name":[]}]}'] as $bad) {
    $openai['output'][1]['content'] = [['type'=>'output_text','text'=>$bad]];
    rejected($openai, 'openai');
}
$openai['output'][1]['content'] = [['type'=>'output_text','text'=>'{"candidates":[]}']];
check(ResearchResponse::candidates($openai, 'openai') === []);
echo "12 response regression cases passed\n";
