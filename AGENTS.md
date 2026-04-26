# AI SEO GEO Optimizer - Codex Development Guide

你是一名资深 WordPress 插件开发工程师、SEO 技术顾问、安全审查工程师。

本项目要开发一款 WordPress 插件：

Plugin Name:
AI SEO GEO Optimizer

插件用途：
用于 WordPress 外贸网站的文章、页面、WooCommerce 产品内容优化。插件需要罗列所有文章、页面和产品，支持按分类、产品分类、标签、关键词、内容类型筛选。管理员可以选择单篇或多篇内容，通过不同 AI 平台 API 生成 SEO/GEO 优化建议，包括标题、SEO 标题、Meta Description、标签、摘要、正文、FAQ、内链建议、图片 Alt 文本建议、Schema 建议、真实性检查和人工审核提示。

重要原则：
1. AI 不允许直接覆盖线上内容。
2. 所有 AI 结果必须先进入审核页面。
3. 管理员必须可以逐项选择是否应用：
   - 标题
   - SEO Title
   - Meta Description
   - 标签
   - 摘要
   - 正文
   - FAQ
   - 内链建议
   - 图片 Alt
   - Schema 建议
4. 每次应用修改前必须保存原始内容快照。
5. 必须支持回滚。
6. 必须保存优化日志。
7. 必须遵守 WordPress Coding Standards。
8. 所有后台操作必须做：
   - current_user_can() 权限检查
   - nonce 校验
   - 输入 sanitize
   - 输出 escape
9. WooCommerce 未启用时，产品相关功能自动隐藏，不得报错。
10. Yoast SEO、Rank Math 未启用时，不得报错。
11. 不使用 Composer。
12. 不使用第三方 PHP SDK。
13. AI API 请求优先使用 WordPress 原生 wp_remote_post()。
14. 不使用 React，不使用复杂前端打包。
15. 第一版以稳定、可安装、可维护为最高优先级。

开发语言与环境：
- PHP 7.4+
- WordPress 6.0+
- WooCommerce 可选兼容
- 前端使用 WordPress Admin 原生 UI + 少量原生 JS
- 不依赖 Node、Composer、Webpack

插件目录结构：

ai-seo-geo-optimizer/
├── ai-seo-geo-optimizer.php
├── uninstall.php
├── readme.txt
├── includes/
│   ├── class-plugin.php
│   ├── class-installer.php
│   ├── class-admin-menu.php
│   ├── class-security.php
│   ├── class-content-query.php
│   ├── class-ai-provider-interface.php
│   ├── class-ai-provider-manager.php
│   ├── class-prompt-builder.php
│   ├── class-optimizer.php
│   ├── class-review-manager.php
│   ├── class-revision-manager.php
│   ├── class-seo-meta-adapter.php
│   ├── class-log-manager.php
│   ├── class-schema-builder.php
│   └── providers/
│       ├── class-openai-provider.php
│       ├── class-deepseek-provider.php
│       ├── class-qwen-provider.php
│       └── class-custom-provider.php
├── admin/
│   ├── pages/
│   │   ├── dashboard.php
│   │   ├── content-list.php
│   │   ├── review-apply.php
│   │   ├── providers.php
│   │   ├── prompt-templates.php
│   │   └── logs.php
│   └── assets/
│       ├── admin.css
│       └── admin.js
├── templates/
│   ├── prompt-post-seo.txt
│   ├── prompt-product-seo.txt
│   ├── prompt-meta-only.txt
│   ├── prompt-geo.txt
│   ├── prompt-faq.txt
│   └── prompt-image-alt.txt
└── languages/
    └── ai-seo-geo-optimizer.pot

数据库表：
所有表名必须使用 $wpdb->prefix，不得写死 wp_。

1. {prefix}ai_seo_providers
字段：
- id BIGINT UNSIGNED AUTO_INCREMENT
- provider_key VARCHAR(100)
- provider_name VARCHAR(200)
- base_url TEXT
- api_key_encrypted TEXT
- default_model VARCHAR(200)
- timeout INT DEFAULT 60
- status VARCHAR(20) DEFAULT 'active'
- created_at DATETIME
- updated_at DATETIME

2. {prefix}ai_seo_jobs
字段：
- id BIGINT UNSIGNED AUTO_INCREMENT
- post_id BIGINT UNSIGNED
- post_type VARCHAR(50)
- provider_key VARCHAR(100)
- model VARCHAR(200)
- status VARCHAR(50)
- target_keyword TEXT
- language VARCHAR(50)
- fields_json LONGTEXT
- result_json LONGTEXT
- error_message LONGTEXT
- created_by BIGINT UNSIGNED
- created_at DATETIME
- updated_at DATETIME

3. {prefix}ai_seo_snapshots
字段：
- id BIGINT UNSIGNED AUTO_INCREMENT
- post_id BIGINT UNSIGNED
- job_id BIGINT UNSIGNED
- old_title LONGTEXT
- old_content LONGTEXT
- old_excerpt LONGTEXT
- old_meta_json LONGTEXT
- old_terms_json LONGTEXT
- created_at DATETIME

4. {prefix}ai_seo_logs
字段：
- id BIGINT UNSIGNED AUTO_INCREMENT
- job_id BIGINT UNSIGNED
- post_id BIGINT UNSIGNED
- action VARCHAR(100)
- message LONGTEXT
- context_json LONGTEXT
- created_by BIGINT UNSIGNED
- created_at DATETIME

AI Provider 要求：
插件必须支持：
1. OpenAI
2. DeepSeek
3. Qwen / 通义千问
4. 自定义 OpenAI-compatible API

每个 Provider 配置字段：
- provider_key
- provider_name
- base_url
- api_key
- default_model
- timeout
- status

API Key 安全要求：
1. 后台列表中不得显示完整 API Key。
2. 日志中不得记录完整 API Key。
3. 编辑时只显示 masked 状态。
4. 保存前必须 sanitize。
5. 测试连接时不得泄露完整请求头。

AI 输出必须是 valid JSON only，不允许 Markdown，不允许解释性文字。

AI 标准返回结构：

{
  "search_intent": "informational | commercial | product | transactional",
  "primary_keyword": "",
  "secondary_keywords": [],
  "seo_title": "",
  "meta_description": "",
  "slug_suggestion": "",
  "h1": "",
  "suggested_tags": [],
  "excerpt": "",
  "outline": [
    {
      "heading": "",
      "purpose": ""
    }
  ],
  "optimized_content": "",
  "faq": [
    {
      "question": "",
      "answer": ""
    }
  ],
  "internal_link_suggestions": [
    {
      "anchor": "",
      "target_url": "",
      "reason": ""
    }
  ],
  "image_alt_suggestions": [
    {
      "image_id": "",
      "alt_text": "",
      "reason": ""
    }
  ],
  "schema_suggestion": {
    "@type": "",
    "fields": {}
  },
  "geo_summary": "",
  "fact_check_notes": [],
  "needs_human_review": [],
  "unsupported_claims_removed": [],
  "content_score": {
    "seo_score": 0,
    "geo_score": 0,
    "readability_score": 0,
    "trust_score": 0,
    "risk_score": 0
  },
  "risk_level": "low | medium | high"
}

内容真实性规则：
AI 生成内容必须像真人 SEO 编辑整理后的内容，而不是 AI 批量生成的模板文章。

必须严格遵守：
1. 不得编造任何产品参数。
2. 不得编造认证证书。
3. 不得编造客户案例。
4. 不得编造出口国家。
5. 不得编造价格、库存、交期。
6. 不得编造公司历史、工厂规模、员工数量、生产能力。
7. 不得把行业常识写成本公司的真实承诺。
8. 不得使用无法证明的夸张词，例如：
   - best in the world
   - No.1
   - 100% guaranteed
   - unmatched quality
   - revolutionary
9. 可以优化表达、结构、标题、小标题、段落顺序、关键词布局。
10. 不得改变原文事实。
11. 如果原文没有提供某个事实，AI 不允许自行补充具体数字。
12. 不确定内容必须写入 needs_human_review。
13. 被删除的夸张或无依据内容必须写入 unsupported_claims_removed。
14. 输出内容应符合人工编辑质量，可以让管理员审核后发布。
15. 目标不是生成更多内容，而是把现有内容优化成真实、有用、结构清晰、像人工编辑过的 SEO 页面。

SEO 规则：
1. 识别搜索意图：
   - informational
   - commercial
   - product
   - transactional
2. SEO Title：
   - 包含主关键词
   - 简洁自然
   - 不夸张
   - 不重复
   - 与正文主题一致
3. Meta Description：
   - 120-155 个英文字符左右
   - 包含主关键词
   - 像真人写的搜索摘要
   - 不堆砌关键词
   - 不虚假承诺
4. 正文首段必须直接说明：
   - 这个产品/主题是什么
   - 用于什么场景
   - 适合什么用户
   - 本页面能解决什么问题
5. 正文必须有清晰 H2/H3 层级。
6. 正文应增加真实有用的信息模块：
   - What is it?
   - Main Features
   - Applications
   - How to Choose
   - Buyer Considerations
   - FAQ
7. 不得生成大量相似页面。
8. 不得只替换国家名、城市名、型号名制造低质量页面。
9. 不得添加隐藏文字。
10. 不得关键词堆砌。
11. 不得生成没有用户价值的长文。

GEO 规则：
1. 使用清晰定义。
2. 首段直接回答主题。
3. 增加 Quick Answer 类型段落。
4. 使用结构化小标题。
5. 增加 FAQ。
6. 增加术语解释。
7. 使用简洁、明确、可被 AI 摘要引用的段落。
8. 减少空话和营销废话。
9. 保留真实产品信息。
10. 增加对比、选择建议、采购注意事项。
11. 输出 geo_summary 字段。
12. 输出 fact_check_notes 字段。

安全要求：
1. 所有后台页面必须 current_user_can('manage_options') 或对应编辑权限。
2. 所有表单必须使用 wp_nonce_field()。
3. 所有提交必须 check_admin_referer() 或 check_ajax_referer()。
4. 输入必须 sanitize。
5. 输出必须 escape。
6. 正文 HTML 可以使用 wp_kses_post()。
7. URL 使用 esc_url()。
8. 属性使用 esc_attr()。
9. 普通文本使用 esc_html()。
10. 不能让低权限用户读取 API Key。
11. 不能让低权限用户执行 AI 优化。
12. 不能让低权限用户应用修改或回滚。
13. 日志不得保存完整 API Key。
14. 删除、回滚、应用修改都必须写日志。

SEO 插件兼容：
插件需要检测：
1. Yoast SEO
2. Rank Math
3. All in One SEO
4. SEOPress

第一版必须至少兼容：
1. Yoast SEO
   - _yoast_wpseo_title
   - _yoast_wpseo_metadesc
2. Rank Math
   - rank_math_title
   - rank_math_description
   - rank_math_focus_keyword

没有 SEO 插件时：
保存到本插件自定义 meta：
- _ai_seo_geo_title
- _ai_seo_geo_description
- _ai_seo_geo_focus_keyword

第一阶段 MVP 范围：
必须完成：
1. 插件可安装、可启用。
2. 后台出现 AI SEO Optimizer 菜单。
3. 创建数据库表。
4. AI Providers 设置页可添加 OpenAI-compatible 配置。
5. 内容列表页可列出文章、页面、WooCommerce 产品。
6. 支持分类、产品分类、关键词、内容类型筛选。
7. 支持单篇内容优化。
8. 支持调用 AI API。
9. 支持展示 AI 返回 JSON。
10. 支持审核后应用标题、Meta Description、标签、正文。
11. 应用前保存快照。
12. 支持回滚。
13. 支持日志记录。
14. 兼容 Yoast SEO 和 Rank Math 的 SEO Title / Meta Description 字段。
15. WooCommerce 未启用时不得报错。

第一阶段暂时不要开发：
1. 真正的批量队列。
2. Token 费用统计。
3. 多站点 SaaS。
4. 复杂图表。
5. React 后台。
6. Composer 依赖。
7. 第三方 SDK。
8. 自动发布大量内容。

开发方式：
1. 每个阶段完成后必须列出创建/修改的文件。
2. 每个阶段完成后必须说明如何测试。
3. 不要一次性开发所有阶段。
4. 修改已有文件前先阅读现有代码。
5. 不得删除已有功能。
6. 代码必须完整，不要省略。
7. 不要输出伪代码。
8. 不要输出“你可以自己补充”的占位内容。
9. 所有类名、函数名必须加插件前缀，避免污染 WordPress 全局命名空间。
10. 所有数据库操作必须使用 $wpdb->prepare()，除 dbDelta 建表外。
