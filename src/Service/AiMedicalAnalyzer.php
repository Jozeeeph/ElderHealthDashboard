<?php

namespace App\Service;

class AiMedicalAnalyzer
{
    private array $symptoms = [

        'urgence'=>[
            'poitrine','respirer','étouffer','saignement','évanoui','convulsion'
        ],

        'important'=>[
            'vertige','fièvre','vomissement','douleur','coeur','pression'
        ],

        'léger'=>[
            'toux','rhume','mal de gorge','stress','fatigue','migraine'
        ]
    ];


    private array $intentKeywords = [

        'question'=>[
            'est-ce','grave','danger','risque'
        ],

        'advice'=>[
            'que faire','quoi faire','solution','traitement'
        ],

        'consult'=>[
            'dois-je consulter','médecin','urgence','hôpital'
        ]
    ];


    private array $baseAdvice = [

        'urgence'=>"🚨 Symptômes potentiellement graves détectés.",
        'important'=>"⚠️ Vos symptômes nécessitent une attention médicale.",
        'léger'=>"ℹ️ Vos symptômes semblent légers.",
        'none'=>"Merci pour votre message."
    ];


    public function analyze(string $text): array
    {
        $text = strtolower($text);

        $detected=[];
        $level='none';

        foreach($this->symptoms as $severity=>$words){
            foreach($words as $word){
                if(str_contains($text,$word)){
                    $detected[]=$word;
                    $level=$this->highestLevel($level,$severity);
                }
            }
        }

        return [
            'level'=>$level,
            'detected'=>array_unique($detected),
            'intent'=>$this->detectIntent($text)
        ];
    }


    private function detectIntent(string $text): string
    {
        foreach($this->intentKeywords as $intent=>$words){
            foreach($words as $word){
                if(str_contains($text,$word)){
                    return $intent;
                }
            }
        }
        return 'info';
    }


    private function highestLevel(string $current,string $new): string
    {
        $priority=['none'=>0,'léger'=>1,'important'=>2,'urgence'=>3];
        return $priority[$new] > $priority[$current] ? $new : $current;
    }


    public function generateReply(string $text): string
    {
        $analysis=$this->analyze($text);

        $level=$analysis['level'];
        $intent=$analysis['intent'];
        $symptoms=$analysis['detected'];

        $response=$this->baseAdvice[$level];

        // réponse selon intention
        if($intent==='question'){
            $response.=" Cela peut nécessiter une évaluation médicale.";
        }

        elseif($intent==='advice'){
            $response.=" Nous vous conseillons de vous reposer, boire de l’eau et surveiller l’évolution.";
        }

        elseif($intent==='consult'){
            $response.=" Oui, il est recommandé de consulter un professionnel de santé.";
        }

        else{
            if($level==='important' || $level==='urgence'){
                $response.=" Consultez un professionnel de santé.";
            }
        }

        if(!empty($symptoms)){
            $response.="\n\nSymptômes détectés : ".implode(', ',$symptoms).".";
        }

        return $response;
    }

    public function needsMedicalReply(string $text): bool
    {
        return $this->analyze($text)['level'] !== 'none';
    }

}
