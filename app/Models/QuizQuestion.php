<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'type',
        'question',
        'options',
        'correct_answer',
        'explanation',
        'points',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_answer' => 'array',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function checkAnswer($answer)
    {
        switch ($this->type) {
            case 'multiple_choice':
            case 'true_false':
                return $answer === $this->correct_answer[0];
            case 'short_answer':
                return strtolower(trim($answer)) === strtolower(trim($this->correct_answer[0]));
            case 'matching':
            case 'ordering':
                return $answer === $this->correct_answer;
            default:
                return false;
        }
    }
}
