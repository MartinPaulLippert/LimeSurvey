<?php

namespace ls\components;

use ArrayAccess;
use CHtml;
use CHttpException;
use Exception;
use PluginEvent;
use ReflectionClass;
use ls\models\SettingGlobal;
use ls\components\SurveySession;
use TbHtml;

/**
 *
 * This class manages all information needed to render a question.
 * @todo Remove dependency on $_question.
 * @todo Remove array access (this was added because it was the way replacements used to work).
 */
class RenderedQuestion implements ArrayAccess
{
    /**
     * @var int The index of this question in the survey.
     */
    protected $_index;
    /**
     * The HTML template for the question.
     * @var string
     */
    protected $_template;
    /**
     * The HTML for the question.
     * @var
     */
    protected $_html;

    /**
     * The validation messages.
     * Keys are the javascript expression, values the messages.
     * @var array
     */
    protected $_validations = [];

    protected $_text;
    /**
     * @var \ls\models\Question
     */
    private $_question;

    /**
     * The html options for this question.
     * @var array
     */
    public $htmlOptions = [];

    public function __construct(\ls\models\Question $question)
    {
        $this->_question = $question;
    }

    public function setQuestionText($text)
    {
        $this->_text = $text;
    }

    public function addValidation($javascript, $message = null)
    {
        $this->_validations[$javascript] = $message;
    }


    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return in_array($offset, ['html', 'messages']);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return string
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        switch ($offset) {
            case 'html':
                $result = $this->_html;
                break;
            case 'messages':
                $result = $this->getMessages();
                break;
            case 'text':
                $result = $this->_text;
                break;
            case 'help':
                $result = "@TODO";

        }

        return $result;
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception("Cannot set values.");
    }


    public function offsetUnset($offset)
    {
        throw new \Exception("Cannot unset values");
    }

    public function getMessages()
    {
        $result = '';

        foreach ($this->_validations as $expression => $message) {
            /**
             * Render with irrelevance-expression, so the message gets shown automatically.
             *
             */
            $result .= TbHtml::tag('span', [
                'class' => 'validation-message irrelevant',
                'data-irrelevance-expression' => $expression,
            ], $message);
        }

        return $result;
    }

    public function setHtml($html)
    {
        $this->_html = $html;
    }

    /**
     * Gets the replacements for rendering this question.
     * @return array
     * @param SurveySession $session
     * @throws CHttpException
     * @throws Exception
     */
    protected function getReplacements(SurveySession $session)
    {
        bP();
        $survey = $this->_question->survey;

        $replacements = $this->_question->group->getReplacements(\LimeExpressionManager::getExpressionManagerForSession($session));
        // Core value : not replaced
        $replacements['QID'] = $this->_question->primaryKey;
        $replacements['GID'] = $this->_question->gid;
        $replacements['SGQ'] = $this->_question->sgqa;
        $replacements['QUESTION_CODE'] = null;
        $replacements['QUESTION_NUMBER'] = null;

        $iNumber = $this->_index + 1; // Nondevelopers people start counting from 1 for some reason..
        switch (SettingGlobal::get('showqnumcode', 'choose')) {
            case 'both':
                $replacements['QUESTION_CODE'] = $this->_question->title;
                $replacements['QUESTION_NUMBER'] = $iNumber;
                break;
            case 'number':
                $replacements['QUESTION_NUMBER'] = $iNumber;
                $replacements['QUESTION_CODE'] = $this->_question->title;
                break;
            case 'choose':
                switch ($survey->showqnumcode) {
                    case 'B': // Both
                        $replacements['QUESTION_CODE'] = $this->_question->title;
                        $replacements['QUESTION_NUMBER'] = $iNumber;
                        break;
                    case 'N':
                        $replacements['QUESTION_NUMBER'] = $iNumber;
                        break;
                    case 'C':
                        $replacements['QUESTION_CODE'] = $this->_question->title;
                        break;
                    case 'X':
                    default:
                        break;
                }
                break;
        }
        // Core value : user text
        $replacements['QUESTION_TEXT'] = $this['text'];
        $replacements['QUESTIONHELP'] = $this->_question->help;// ls\models\User help

        $classes = $this->_question->classes;
        $replacements['QUESTION_CLASS'] = implode(' ', $classes);

        $session = App()->surveySessionManager->current;

        // Core value : LS text : EM and not
        if (empty($this['html'])) {
            $rc = new ReflectionClass($this->_question);
            $url = "https://github.com/LimeSurvey/LimeSurvey/blob/develop/application/models/questions/{$rc->getShortName()}.php";
            throw new \CHttpException(500,
                "No HTML found. Is " . get_class($this->_question) . "::render() implemented in $url");
        }

        $replacements['ANSWER'] = $this['html'];
        $replacements['QUESTION_HELP'] = $this['help'];
        $replacements['QUESTION_VALID_MESSAGE'] = $this->getMessages();

        // For QUESTION_ESSENTIALS
        $htmlOptions = $this->htmlOptions;
        if (true !== $relevance = $this->_question->getRelevanceScript()) {
            $htmlOptions['data-relevance-expression'] = $relevance;
        }

        // Launch the event
        $event = new PluginEvent('beforeQuestionRender');
        // Some helper
        $event->set('question', $this->_question);
        // ls\models\User text
        $event->set('text', $replacements['QUESTION_TEXT']);
        $event->set('questionhelp', $replacements['QUESTIONHELP']);
        // The classes
        $event->set('class', $replacements['QUESTION_CLASS']);
        // LS core text
        $event->set('html', $replacements['ANSWER']);
        $event->set('help', $replacements['QUESTION_HELP']);
        $event->set('valid_message', $replacements['QUESTION_VALID_MESSAGE']);
        // htmlOptions for container
        $event->set('htmlOptions', $htmlOptions);

        $event->dispatch();
        // ls\models\User text
        $replacements['QUESTION_TEXT'] = $event->get('text');
        $replacements['QUESTIONHELP'] = $event->get('questionhelp');
        $replacements['QUESTIONHELPPLAINTEXT'] = strip_tags(addslashes($replacements['QUESTIONHELP']));
        // The classes
        $replacements['QUESTION_CLASS'] = $event->get('class');
        // LS core text
        $replacements['ANSWER'] = $event->get('html');
        $replacements['QUESTION_HELP'] = $event->get('help');
        $replacements['QUESTION_VALID_MESSAGE'] = $event->get('valid_message');
        // Always add id for QUESTION_ESSENTIALS
        $htmlOptions['id'] = "question{$this->_question->primaryKey}";
        $replacements['QUESTION_ESSENTIALS'] = CHtml::renderAttributes($htmlOptions);

        eP();

        return $replacements;
    }

    public function setTemplate($template)
    {
        $this->_template = $template;
    }

    /**
     * Renders the question HTML into the template and returns the result.
     * @return string
     * @throws CHttpException
     */
    public function render(SurveySession $session)
    {
        return \ls\helpers\Replacements::templatereplace($this->_template, $this->getReplacements($session), [],
            $this->_question->qid, $session);
    }

    public function setIndex($i)
    {
        $this->_index = $i;
    }


}