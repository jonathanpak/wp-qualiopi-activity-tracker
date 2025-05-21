<?php
/**
 * LearnDash Integration
 *
 * @package User_Activity_Logger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * LearnDash Integration class
 */
class UAL_LearnDash_Integration {
    
    /**
     * Activity logger instance
     *
     * @var UAL_Activity_Logger
     */
    private $activity_logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->activity_logger = user_activity_logger()->activity_logger;
        
        // Add hooks for LearnDash integration
        add_action('learndash_lesson_completed', array($this, 'log_lesson_completed'), 10, 1);
        add_action('learndash_topic_completed', array($this, 'log_topic_completed'), 10, 1);
        add_action('learndash_quiz_completed', array($this, 'log_quiz_completed'), 10, 2);
        add_action('learndash_course_completed', array($this, 'log_course_completed'), 10, 1);
    }
    
    /**
     * Log lesson completed
     *
     * @param array $data Lesson completion data
     */
    public function log_lesson_completed($data) {
        if (empty($data['user']) || empty($data['lesson']) || empty($data['course'])) {
            return;
        }

        // Get lesson details
        $lesson_id = $data['lesson'];
        $lesson = get_post($lesson_id);
        
        if (!$lesson) {
            return;
        }
        
        // Get course details
        $course_id = $data['course'];
        $course = get_post($course_id);
        
        if (!$course) {
            return;
        }
        
        // Prepare activity data
        $activity_data = array(
            'lesson_id' => $lesson_id,
            'course_id' => $course_id,
            'course_title' => $course->post_title,
        );
        
        // Log the activity
        $this->activity_logger->log_learndash_activity(
            'lesson_completed',
            $lesson_id,
            $lesson->post_title,
            get_permalink($lesson_id),
            $activity_data
        );
    }
    
    /**
     * Log topic completed
     *
     * @param array $data Topic completion data
     */
    public function log_topic_completed($data) {
        if (empty($data['user']) || empty($data['topic']) || empty($data['lesson']) || empty($data['course'])) {
            return;
        }

        // Get topic details
        $topic_id = $data['topic'];
        $topic = get_post($topic_id);
        
        if (!$topic) {
            return;
        }
        
        // Get course details
        $course_id = $data['course'];
        $course = get_post($course_id);
        
        if (!$course) {
            return;
        }
        
        // Get lesson details
        $lesson_id = $data['lesson'];
        $lesson = get_post($lesson_id);
        
        // Prepare activity data
        $activity_data = array(
            'topic_id' => $topic_id,
            'lesson_id' => $lesson_id,
            'lesson_title' => $lesson ? $lesson->post_title : '',
            'course_id' => $course_id,
            'course_title' => $course->post_title,
        );
        
        // Log the activity
        $this->activity_logger->log_learndash_activity(
            'topic_completed',
            $topic_id,
            $topic->post_title,
            get_permalink($topic_id),
            $activity_data
        );
    }
    
    /**
     * Log quiz completed
     *
     * @param array $quiz_data Quiz data
     * @param object $user User object
     */
    public function log_quiz_completed($quiz_data, $user) {
        if (empty($quiz_data) || empty($user)) {
            return;
        }
        
        // Extract quiz ID
        $quiz_id = isset($quiz_data['quiz']) ? $quiz_data['quiz'] : 0;
        
        if (!$quiz_id) {
            return;
        }
        
        // Get quiz post object
        $quiz = get_post($quiz_id);
        
        if (!$quiz) {
            return;
        }
        
        // Get course ID
        $course_id = isset($quiz_data['course']) ? $quiz_data['course'] : 0;
        $course = $course_id ? get_post($course_id) : null;
        
        // Determine if the user passed
        $passed = false;
        if (isset($quiz_data['pass'])) {
            $passed = (bool) $quiz_data['pass'];
        }
        
        // Get score
        $score = isset($quiz_data['score']) ? $quiz_data['score'] : 0;
        $percentage = isset($quiz_data['percentage']) ? $quiz_data['percentage'] : 0;
        
        // Prepare activity data
        $activity_data = array(
            'quiz_id' => $quiz_id,
            'course_id' => $course_id,
            'course_title' => $course ? $course->post_title : '',
            'score' => $score,
            'percentage' => $percentage,
            'passed' => $passed,
        );
        
        // Log the activity
        $this->activity_logger->log_learndash_activity(
            'quiz_completed',
            $quiz_id,
            $quiz->post_title,
            get_permalink($quiz_id),
            $activity_data
        );
    }
    
    /**
     * Log course completed
     *
     * @param array $data Course completion data
     */
    public function log_course_completed($data) {
        if (empty($data['user']) || empty($data['course'])) {
            return;
        }

        // Get course details
        $course_id = $data['course'];
        $course = get_post($course_id);
        
        if (!$course) {
            return;
        }
        
        // Prepare activity data
        $activity_data = array(
            'course_id' => $course_id,
            'completed_on' => current_time('mysql', true),
        );
        
        // Log the activity
        $this->activity_logger->log_learndash_activity(
            'course_completed',
            $course_id,
            $course->post_title,
            get_permalink($course_id),
            $activity_data
        );
    }
}