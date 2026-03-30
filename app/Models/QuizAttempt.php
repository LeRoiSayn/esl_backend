<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'student_id',
        'answers',
        'score',
        'correct_count',
        'total_questions',
        'started_at',
        'completed_at',
        'status',
        'tab_switches',
    ];

    protected $casts = [
        'answers' => 'array',
        'score' => 'float',
        'correct_count' => 'integer',
        'total_questions' => 'integer',
        'tab_switches' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function calculateScore()
    {
        $quiz = $this->quiz()->with('questions')->first();
        $correctCount = 0;
        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($quiz->questions as $question) {
            $totalPoints += $question->points;
            $answer = $this->answers[$question->id] ?? null;
            
            if ($question->checkAnswer($answer)) {
                $correctCount++;
                $earnedPoints += $question->points;
            }
        }

        $this->correct_count = $correctCount;
        $this->total_questions = $quiz->questions->count();
        $this->score = ($totalPoints > 0) ? ($earnedPoints / $totalPoints) * $quiz->total_points : 0;
        $this->save();

        return $this->score;
    }
}
