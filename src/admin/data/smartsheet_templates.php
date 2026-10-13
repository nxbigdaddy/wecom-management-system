<?php
/**
 * 智能表格模板预设
 * 
 * 预定义的智能表格 Schema，用于快速创建工作流表格。
 * 每个模板包含：子表定义、字段定义（标题+类型）
 * 
 * 来源参考：wecomcli_crm 工作流设计
 */
return [
    // ===== 1. 健康日记 =====
    'health_diary' => [
        'name' => '健康日记',
        'desc' => '记录饮食与身体状态，发现健康关联',
        'icon' => 'fa-heartbeat',
        'sheets' => [
            [
                'title' => '饮食记录',
                'fields' => [
                    ['field_title' => '日期',       'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '餐次',       'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '食物列表',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '份量说明',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '热量估算',    'field_type' => 'FIELD_TYPE_NUMBER'],
                    ['field_title' => '备注',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
            [
                'title' => '身体状态',
                'fields' => [
                    ['field_title' => '记录时间',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '状态描述',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '严重程度',    'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '持续时长',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '关联食物',    'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
            [
                'title' => '关联分析',
                'fields' => [
                    ['field_title' => '食物',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '状态',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '出现次数',    'field_type' => 'FIELD_TYPE_NUMBER'],
                    ['field_title' => '关联强度',    'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '更新时间',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                ],
            ],
        ],
    ],

    // ===== 2. 个人CRM =====
    'personal_crm' => [
        'name' => '个人CRM',
        'desc' => '联系人管理与关系热度追踪',
        'icon' => 'fa-address-card',
        'sheets' => [
            [
                'title' => '联系人',
                'fields' => [
                    ['field_title' => '姓名',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '手机号',      'field_type' => 'FIELD_TYPE_PHONE_NUMBER'],
                    ['field_title' => '邮箱',        'field_type' => 'FIELD_TYPE_EMAIL'],
                    ['field_title' => '公司',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '职位',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '关系热度',    'field_type' => 'FIELD_TYPE_NUMBER'],
                    ['field_title' => '最后联系日',  'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '标签',        'field_type' => 'FIELD_TYPE_MULTI_SELECT'],
                    ['field_title' => '备注',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
            [
                'title' => '互动记录',
                'fields' => [
                    ['field_title' => '联系人',      'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '互动类型',    'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '日期',        'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '摘要',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '下次跟进',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                ],
            ],
        ],
    ],

    // ===== 3. 知识库 =====
    'knowledge_base' => [
        'name' => '知识库',
        'desc' => '链接保存、摘要整理与全文搜索',
        'icon' => 'fa-book',
        'sheets' => [
            [
                'title' => '条目',
                'fields' => [
                    ['field_title' => '标题',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => 'URL',         'field_type' => 'FIELD_TYPE_URL'],
                    ['field_title' => '摘要',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '标签',        'field_type' => 'FIELD_TYPE_MULTI_SELECT'],
                    ['field_title' => '分类',        'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '收藏时间',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '链接文本',    'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
        ],
    ],

    // ===== 4. 会议待办 =====
    'meeting_todo' => [
        'name' => '会议待办',
        'desc' => '从会议提取待办事项并追踪',
        'icon' => 'fa-check-square-o',
        'sheets' => [
            [
                'title' => '待办',
                'fields' => [
                    ['field_title' => '任务名称',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '负责人',      'field_type' => 'FIELD_TYPE_USER'],
                    ['field_title' => '截止日期',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '优先级',      'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '状态',        'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '关联会议',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '备注',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
        ],
    ],

    // ===== 5. 内容管道 =====
    'content_pipeline' => [
        'name' => '内容管道',
        'desc' => '创意评估→方案设计→执行追踪',
        'icon' => 'fa-tasks',
        'sheets' => [
            [
                'title' => '创意',
                'fields' => [
                    ['field_title' => '创意标题',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '描述',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '评估得分',    'field_type' => 'FIELD_TYPE_NUMBER'],
                    ['field_title' => '状态',        'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '创建日期',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                ],
            ],
            [
                'title' => '执行',
                'fields' => [
                    ['field_title' => '项目',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '负责人',      'field_type' => 'FIELD_TYPE_USER'],
                    ['field_title' => '开始日期',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '预计完成',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '进度',        'field_type' => 'FIELD_TYPE_PROGRESS'],
                    ['field_title' => '备注',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
        ],
    ],

    // ===== 6. 股票研究 =====
    'stock_analyzer' => [
        'name' => '股票研究',
        'desc' => '自选股监控与深度研究记录',
        'icon' => 'fa-line-chart',
        'sheets' => [
            [
                'title' => '自选股',
                'fields' => [
                    ['field_title' => '代码',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '名称',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '关注日期',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '目标价',      'field_type' => 'FIELD_TYPE_CURRENCY'],
                    ['field_title' => '买入区间',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '备注',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
            [
                'title' => '研究记录',
                'fields' => [
                    ['field_title' => '股票',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '报告日期',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '评级',        'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '要点',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '参考链接',    'field_type' => 'FIELD_TYPE_URL'],
                ],
            ],
        ],
    ],

    // ===== 7. 社交追踪 =====
    'social_tracker' => [
        'name' => '社交追踪',
        'desc' => '社交媒体账号管理与数据记录',
        'icon' => 'fa-share-alt',
        'sheets' => [
            [
                'title' => '账号',
                'fields' => [
                    ['field_title' => '平台',        'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '名称',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '初始粉丝',    'field_type' => 'FIELD_TYPE_NUMBER'],
                    ['field_title' => '目标粉丝',    'field_type' => 'FIELD_TYPE_NUMBER'],
                    ['field_title' => '备注',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
            [
                'title' => '数据记录',
                'fields' => [
                    ['field_title' => '账号',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '日期',        'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '粉丝数',      'field_type' => 'FIELD_TYPE_NUMBER'],
                    ['field_title' => '互动数',      'field_type' => 'FIELD_TYPE_NUMBER'],
                    ['field_title' => '笔记',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
        ],
    ],

    // ===== 8. 多代理协同 =====
    'multi_agent' => [
        'name' => '多代理协同',
        'desc' => 'AI 多代理任务分派与进度追踪',
        'icon' => 'fa-robot',
        'sheets' => [
            [
                'title' => '任务',
                'fields' => [
                    ['field_title' => '任务名称',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '代理角色',    'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '状态',        'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '优先级',      'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '创建日期',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '完成日期',    'field_type' => 'FIELD_TYPE_DATE_TIME'],
                    ['field_title' => '输出',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
            [
                'title' => '流水线',
                'fields' => [
                    ['field_title' => '名称',        'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '步骤序列',    'field_type' => 'FIELD_TYPE_TEXT'],
                    ['field_title' => '状态',        'field_type' => 'FIELD_TYPE_SELECT'],
                    ['field_title' => '备注',        'field_type' => 'FIELD_TYPE_TEXT'],
                ],
            ],
        ],
    ],
];
