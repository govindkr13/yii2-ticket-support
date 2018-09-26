<?php
/**
 * @author akiraz@bk.ru
 * @link https://github.com/akiraz2/yii2-ticket-support
 * @copyright 2018 akiraz2
 * @license MIT
 */

namespace akiraz2\support\models;

use akiraz2\support\jobs\SendMailJob;
use akiraz2\support\Mailer;
use akiraz2\support\traits\ModuleTrait;
use Yii;

/**
 * This is the model class for table "ticket_content".
 *
 * @property integer|\MongoDB\BSON\ObjectID|string $id
 * @property integer|\MongoDB\BSON\ObjectID|string $id_ticket
 * @property string $content
 * @property string $mail_id
 * @property string $info
 * @property string $fetch_date
 * @property integer|\MongoDB\BSON\ObjectID|string $user_id
 * @property integer|\MongoDB\BSON\UTCDateTime $created_at
 * @property integer|\MongoDB\BSON\UTCDateTime $updated_at
 *
 * @property User $user
 * @property Ticket $ticket
 */
class Content extends ContentBase
{
    use ModuleTrait;

    const STATUS_ACTIVE = 10;
    const STATUS_INACTIVE = 20;

    /**
     * get status text
     * @return string
     */
    public function getStatusText()
    {
        $status = $this->status;
        $list = self::getStatusOption();
        if (!empty($status) && in_array($status, array_keys($list))) {
            return $list[$status];
        }
        return \akiraz2\support\Module::t('support', 'Unknown');
    }

    /**
     * get status list
     * @param null $e
     * @return array
     */
    public static function getStatusOption($e = null)
    {
        $option = [
            self::STATUS_ACTIVE => \akiraz2\support\Module::t('support', 'Active'),
            self::STATUS_INACTIVE => \akiraz2\support\Module::t('support', 'Inactive'),
        ];
        if (is_array($e)) {
            foreach ($e as $i) {
                unset($option[$i]);
            }
        }
        return $option;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_ticket', 'content'], 'required'],
            [['content'], 'string'],
            [
                ['user_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => $this->getModule()->userModel,
                'targetAttribute' => ['user_id' => $this->getModule()->userPK]
            ],
            [
                ['id_ticket'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Ticket::className(),
                'targetAttribute' => ['id_ticket' => $this->getModule()->isMongoDb() ? '_id' : 'id']
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => \akiraz2\support\Module::t('support', 'ID'),
            'id_ticket' => \akiraz2\support\Module::t('support', 'Id Ticket'),
            'content' => \akiraz2\support\Module::t('support', 'Content'),
            'user_id' => \akiraz2\support\Module::t('support', 'Created By'),
            'created_at' => \akiraz2\support\Module::t('support', 'Created At'),
            'updated_at' => \akiraz2\support\Module::t('support', 'Updated At'),
        ];
    }

    /**
     * @return \yii\db\ActiveQueryInterface
     */
    public function getUser()
    {
        return $this->hasOne($this->getModule()->userModel, [$this->getModule()->userPK => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQueryInterface
     */
    public function getTicket()
    {
        if (is_a($this, '\yii\mongodb\ActiveRecord')) {
            return $this->hasOne(Ticket::className(), ['_id' => 'id_ticket']);
        } else {
            return $this->hasOne(Ticket::className(), ['id' => 'id_ticket']);
        }

    }

    /**
     * @inheritdoc
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes); // TODO: Change the autogenerated stub
        if ($insert) {
            if ($this->getModule()->notifyByEmail) {
                if ($this->user_id != $this->ticket->user_id) {
                    $id = Yii::$app->get($this->getModule()->queueComponent)->push(new SendMailJob([
                        'contentId' => $this->id
                    ]));
                }
            }
        }
    }

    public function getUsername()
    {
        $showUserSupport = $this->getModule()->showUsernameSupport;
        $username = !empty($this->user_id) ? $this->user->{$this->getModule()->userName} : $this->ticket->getNameEmail();
        if(!$this->isOwn() && !$showUserSupport) {
            $username = $this->getModule()->userNameSupport;
        }
        return $username;
    }

    public function isOwn()
    {
        return $this->user_id == $this->ticket->user_id;
    }

    protected function getMailer()
    {
        return \Yii::$container->get(Mailer::className());
    }
}
