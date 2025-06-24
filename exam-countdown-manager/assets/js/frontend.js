/**
 * 資格試験カウントダウンマネージャー - フロントエンドJavaScript
 *
 * @package ExamCountdownManager
 */

(function($) {
    'use strict';

    // カウントダウン管理オブジェクト
    const ExamCountdown = {
        timers: [],
        updateInterval: 1000, // 1秒
        
        /**
         * 初期化
         */
        init: function() {
            this.initCountdowns();
            this.initResponsive();
            this.initAccessibility();
            this.initAnimations();
            
            // 1分ごとに更新
            setInterval(() => {
                this.updateAllCountdowns();
            }, 60000);
        },
        
        /**
         * カウントダウンの初期化
         */
        initCountdowns: function() {
            $('.ecm-countdown[data-exam-date]').each((index, element) => {
                const $countdown = $(element);
                const examDate = $countdown.data('exam-date');
                const showTime = $countdown.data('show-time') === 'true';
                
                this.setupCountdown($countdown, examDate, showTime);
            });
        },
        
        /**
         * 個別カウントダウンのセットアップ
         */
        setupCountdown: function($countdown, examDate, showTime) {
            const targetDate = new Date(examDate + ' 00:00:00').getTime();
            
            const updateFunction = () => {
                this.updateSingleCountdown($countdown, targetDate, showTime);
            };
            
            // 初回実行
            updateFunction();
            
            // タイマー登録
            if (showTime) {
                const timerId = setInterval(updateFunction, this.updateInterval);
                this.timers.push(timerId);
                $countdown.data('timer-id', timerId);
            }
        },
        
        /**
         * 単一カウントダウンの更新
         */
        updateSingleCountdown: function($countdown, targetDate, showTime) {
            const now = new Date().getTime();
            const distance = targetDate - now;
            
            if (distance < 0) {
                this.handleExpiredCountdown($countdown);
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            if (showTime && $countdown.find('.ecm-countdown-detailed').length > 0) {
                this.updateDetailedDisplay($countdown, days, hours, minutes, seconds);
            } else {
                this.updateSimpleDisplay($countdown, days);
            }
            
            // 緊急度に応じたスタイル適用
            this.applyUrgencyStyles($countdown, days);
        },
        
        /**
         * 詳細表示の更新
         */
        updateDetailedDisplay: function($countdown, days, hours, minutes, seconds) {
            const $timeUnits = $countdown.find('.ecm-time-unit');
            
            if ($timeUnits.length >= 3) {
                $timeUnits.eq(0).find('.ecm-number').text(days);
                $timeUnits.eq(1).find('.ecm-number').text(hours);
                $timeUnits.eq(2).find('.ecm-number').text(minutes);
                
                if ($timeUnits.length >= 4) {
                    $timeUnits.eq(3).find('.ecm-number').text(seconds);
                }
            }
        },
        
        /**
         * シンプル表示の更新
         */
        updateSimpleDisplay: function($countdown, days) {
            const $daysNumber = $countdown.find('.ecm-days-number');
            if ($daysNumber.length > 0) {
                $daysNumber.text(days);
            }
        },
        
        /**
         * 期限切れカウントダウンの処理
         */
        handleExpiredCountdown: function($countdown) {
            const timerId = $countdown.data('timer-id');
            if (timerId) {
                clearInterval(timerId);
                this.timers = this.timers.filter(id => id !== timerId);
            }
            
            $countdown.removeClass('ecm-countdown').addClass('ecm-countdown-finished');
            
            const $content = $countdown.find('.ecm-countdown-detailed, .ecm-countdown-simple');
            $content.html('<div class="ecm-finished-message">試験終了</div>');
            
            // 期限切れイベントを発火
            $countdown.trigger('exam:expired');
        },
        
        /**
         * 緊急度スタイルの適用
         */
        applyUrgencyStyles: function($countdown, days) {
            $countdown.removeClass('ecm-urgent ecm-very-urgent');
            
            if (days <= 3) {
                $countdown.addClass('ecm-very-urgent');
            } else if (days <= 7) {
                $countdown.addClass('ecm-urgent');
            }
        },
        
        /**
         * 全カウントダウンの更新
         */
        updateAllCountdowns: function() {
            $('.ecm-countdown[data-exam-date]').each((index, element) => {
                const $countdown = $(element);
                const examDate = $countdown.data('exam-date');
                const showTime = $countdown.data('show-time') === 'true';
                const targetDate = new Date(examDate + ' 00:00:00').getTime();
                
                this.updateSingleCountdown($countdown, targetDate, showTime);
            });
        },
        
        /**
         * レスポンシブ対応の初期化
         */
        initResponsive: function() {
            const resizeHandler = () => {
                this.adjustCountdownSize();
                this.adjustExamListLayout();
            };
            
            $(window).on('resize', this.debounce(resizeHandler, 250));
            resizeHandler(); // 初回実行
        },
        
        /**
         * カウントダウンサイズの調整
         */
        adjustCountdownSize: function() {
            const windowWidth = $(window).width();
            
            $('.ecm-countdown').each(function() {
                const $countdown = $(this);
                
                if (windowWidth < 768) {
                    $countdown.addClass('ecm-mobile');
                } else {
                    $countdown.removeClass('ecm-mobile');
                }
            });
        },
        
        /**
         * 試験一覧レイアウトの調整
         */
        adjustExamListLayout: function() {
            const windowWidth = $(window).width();
            
            $('.ecm-exam-list').each(function() {
                const $list = $(this);
                
                if (windowWidth < 768) {
                    $list.removeClass('ecm-columns-2 ecm-columns-3 ecm-columns-4')
                         .addClass('ecm-columns-1');
                }
            });
        },
        
        /**
         * アクセシビリティの初期化
         */
        initAccessibility: function() {
            // ARIA属性の設定
            $('.ecm-countdown').each(function() {
                const $countdown = $(this);
                const examName = $countdown.find('.ecm-exam-name').text();
                
                $countdown.attr({
                    'role': 'timer',
                    'aria-label': `${examName}までのカウントダウン`
                });
            });
            
            // キーボードナビゲーション
            $('.ecm-exam-item').attr('tabindex', '0').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
            
            // スクリーンリーダー用のライブ領域
            this.setupLiveRegion();
        },
        
        /**
         * ライブ領域のセットアップ
         */
        setupLiveRegion: function() {
            if ($('#ecm-live-region').length === 0) {
                $('body').append('<div id="ecm-live-region" aria-live="polite" aria-atomic="true" style="position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;"></div>');
            }
            
            // 1分ごとに残り時間をアナウンス（重要な試験のみ）
            setInterval(() => {
                this.announceUrgentCountdowns();
            }, 60000);
        },
        
        /**
         * 緊急カウントダウンのアナウンス
         */
        announceUrgentCountdowns: function() {
            $('.ecm-countdown.ecm-very-urgent').each(function() {
                const $countdown = $(this);
                const examName = $countdown.find('.ecm-exam-name').text();
                const days = parseInt($countdown.find('.ecm-days-number').text()) || 0;
                
                const message = `${examName}まであと${days}日です。`;
                $('#ecm-live-region').text(message);
            });
        },
        
        /**
         * アニメーションの初期化
         */
        initAnimations: function() {
            // Intersection Observer を使用した表示アニメーション
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            $(entry.target).addClass('ecm-visible');
                        }
                    });
                }, {
                    threshold: 0.1,
                    rootMargin: '50px'
                });
                
                $('.ecm-countdown, .ecm-exam-item').each(function() {
                    observer.observe(this);
                });
            }
            
            // カウントダウン数値の変更アニメーション
            this.initNumberChangeAnimation();
        },
        
        /**
         * 数値変更アニメーションの初期化
         */
        initNumberChangeAnimation: function() {
            $('.ecm-days-number, .ecm-number').each(function() {
                const $number = $(this);
                let lastValue = parseInt($number.text()) || 0;
                
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'childList' || mutation.type === 'characterData') {
                            const newValue = parseInt($number.text()) || 0;
                            if (newValue !== lastValue) {
                                $number.addClass('ecm-number-changed');
                                setTimeout(() => {
                                    $number.removeClass('ecm-number-changed');
                                }, 300);
                                lastValue = newValue;
                            }
                        }
                    });
                });
                
                observer.observe(this, {
                    childList: true,
                    characterData: true,
                    subtree: true
                });
            });
        },
        
        /**
         * デバウンス関数
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        /**
         * クリーンアップ
         */
        destroy: function() {
            this.timers.forEach(timerId => {
                clearInterval(timerId);
            });
            this.timers = [];
        }
    };
    
    // 試験一覧インタラクション
    const ExamList = {
        /**
         * 初期化
         */
        init: function() {
            this.initSorting();
            this.initFiltering();
            this.initSearch();
        },
        
        /**
         * ソート機能の初期化
         */
        initSorting: function() {
            // 将来的な拡張: ソート機能
        },
        
        /**
         * フィルタリング機能の初期化
         */
        initFiltering: function() {
            // 将来的な拡張: フィルタリング機能
        },
        
        /**
         * 検索機能の初期化
         */
        initSearch: function() {
            // 将来的な拡張: 検索機能
        }
    };
    
    // ユーティリティ関数
    const Utils = {
        /**
         * 日付フォーマット
         */
        formatDate: function(date, format) {
            // 簡易的な日付フォーマット
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            
            switch (format) {
                case 'YYYY-MM-DD':
                    return `${year}-${month}-${day}`;
                case 'YYYY/MM/DD':
                    return `${year}/${month}/${day}`;
                case 'MM/DD':
                    return `${month}/${day}`;
                default:
                    return d.toLocaleDateString('ja-JP');
            }
        },
        
        /**
         * 数値を3桁区切りでフォーマット
         */
        formatNumber: function(num) {
            return new Intl.NumberFormat('ja-JP').format(num);
        },
        
        /**
         * 相対時間の表示
         */
        getRelativeTime: function(date) {
            const now = new Date();
            const targetDate = new Date(date);
            const diffTime = targetDate - now;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays < 0) {
                return '終了済み';
            } else if (diffDays === 0) {
                return '今日';
            } else if (diffDays === 1) {
                return '明日';
            } else if (diffDays <= 7) {
                return `${diffDays}日後`;
            } else if (diffDays <= 30) {
                const weeks = Math.floor(diffDays / 7);
                return `約${weeks}週間後`;
            } else if (diffDays <= 365) {
                const months = Math.floor(diffDays / 30);
                return `約${months}ヶ月後`;
            } else {
                const years = Math.floor(diffDays / 365);
                return `約${years}年後`;
            }
        },
        
        /**
         * ローカルストレージの安全な操作
         */
        storage: {
            set: function(key, value) {
                try {
                    localStorage.setItem(key, JSON.stringify(value));
                    return true;
                } catch (e) {
                    console.warn('LocalStorage not available:', e);
                    return false;
                }
            },
            
            get: function(key, defaultValue = null) {
                try {
                    const item = localStorage.getItem(key);
                    return item ? JSON.parse(item) : defaultValue;
                } catch (e) {
                    console.warn('LocalStorage read error:', e);
                    return defaultValue;
                }
            },
            
            remove: function(key) {
                try {
                    localStorage.removeItem(key);
                    return true;
                } catch (e) {
                    console.warn('LocalStorage remove error:', e);
                    return false;
                }
            }
        }
    };
    
    // カスタムイベントシステム
    const EventSystem = {
        events: {},
        
        /**
         * イベントリスナーを追加
         */
        on: function(event, callback) {
            if (!this.events[event]) {
                this.events[event] = [];
            }
            this.events[event].push(callback);
        },
        
        /**
         * イベントを発火
         */
        trigger: function(event, data) {
            if (this.events[event]) {
                this.events[event].forEach(callback => {
                    try {
                        callback(data);
                    } catch (e) {
                        console.error('Event callback error:', e);
                    }
                });
            }
        },
        
        /**
         * イベントリスナーを削除
         */
        off: function(event, callback) {
            if (this.events[event]) {
                this.events[event] = this.events[event].filter(cb => cb !== callback);
            }
        }
    };
    
    // 設定管理
    const Settings = {
        defaults: {
            autoUpdate: true,
            soundNotifications: false,
            showSeconds: false,
            theme: 'default'
        },
        
        /**
         * 設定を取得
         */
        get: function(key) {
            const settings = Utils.storage.get('ecm_settings', this.defaults);
            return key ? settings[key] : settings;
        },
        
        /**
         * 設定を保存
         */
        set: function(key, value) {
            const settings = this.get();
            settings[key] = value;
            Utils.storage.set('ecm_settings', settings);
            
            // 設定変更イベントを発火
            EventSystem.trigger('settings:changed', { key, value });
        },
        
        /**
         * 設定をリセット
         */
        reset: function() {
            Utils.storage.remove('ecm_settings');
            EventSystem.trigger('settings:reset');
        }
    };
    
    // 通知システム（プッシュ通知等の将来的な拡張用）
    const Notifications = {
        /**
         * 初期化
         */
        init: function() {
            this.checkPermission();
            this.setupNotificationEvents();
        },
        
        /**
         * 通知権限をチェック
         */
        checkPermission: function() {
            if (!('Notification' in window)) {
                return false;
            }
            
            if (Notification.permission === 'default') {
                this.requestPermission();
            }
            
            return Notification.permission === 'granted';
        },
        
        /**
         * 通知権限をリクエスト
         */
        requestPermission: function() {
            return Notification.requestPermission().then(permission => {
                Settings.set('notificationPermission', permission);
                return permission === 'granted';
            });
        },
        
        /**
         * 通知を表示
         */
        show: function(title, options = {}) {
            if (!this.checkPermission() || !Settings.get('soundNotifications')) {
                return null;
            }
            
            const notification = new Notification(title, {
                icon: '/wp-content/plugins/exam-countdown-manager/assets/images/icon.png',
                badge: '/wp-content/plugins/exam-countdown-manager/assets/images/badge.png',
                ...options
            });
            
            // 自動で閉じる
            setTimeout(() => {
                notification.close();
            }, 5000);
            
            return notification;
        },
        
        /**
         * 通知イベントのセットアップ
         */
        setupNotificationEvents: function() {
            // 試験が近づいた時の通知
            EventSystem.on('countdown:urgent', (data) => {
                if (data.days <= 7) {
                    this.show(`${data.examName}まであと${data.days}日`, {
                        body: '試験日が近づいています。準備はお済みですか？',
                        tag: 'exam-urgent'
                    });
                }
            });
            
            // 試験当日の通知
            EventSystem.on('countdown:today', (data) => {
                this.show(`本日は${data.examName}です`, {
                    body: '試験頑張ってください！',
                    tag: 'exam-today'
                });
            });
        }
    };
    
    // パフォーマンス監視
    const Performance = {
        /**
         * 初期化
         */
        init: function() {
            this.measureLoadTime();
            this.setupPerformanceObserver();
        },
        
        /**
         * 読み込み時間を測定
         */
        measureLoadTime: function() {
            if ('performance' in window) {
                $(window).on('load', () => {
                    const loadTime = performance.now();
                    console.log(`ECM Frontend loaded in ${loadTime.toFixed(2)}ms`);
                });
            }
        },
        
        /**
         * パフォーマンスオブザーバーのセットアップ
         */
        setupPerformanceObserver: function() {
            if ('PerformanceObserver' in window) {
                const observer = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        if (entry.duration > 100) { // 100ms以上かかった処理をログ
                            console.warn(`Slow operation detected: ${entry.name} took ${entry.duration.toFixed(2)}ms`);
                        }
                    });
                });
                
                try {
                    observer.observe({ entryTypes: ['measure'] });
                } catch (e) {
                    // ブラウザがサポートしていない場合は無視
                }
            }
        }
    };
    
    // メイン初期化
    $(document).ready(function() {
        // 基本機能の初期化
        ExamCountdown.init();
        ExamList.init();
        
        // 拡張機能の初期化
        Notifications.init();
        Performance.init();
        
        // 設定変更のイベントリスナー
        EventSystem.on('settings:changed', function(data) {
            console.log('Setting changed:', data.key, '=', data.value);
            
            // 設定に応じた処理の再実行
            if (data.key === 'autoUpdate') {
                if (data.value) {
                    ExamCountdown.updateAllCountdowns();
                }
            }
        });
        
        // カスタムイベントの設定
        $('.ecm-countdown').on('exam:expired', function() {
            const examName = $(this).find('.ecm-exam-name').text();
            EventSystem.trigger('exam:finished', { examName });
        });
        
        // ページ離脱時のクリーンアップ
        $(window).on('beforeunload', function() {
            ExamCountdown.destroy();
        });
    });
    
    // グローバルに公開（デバッグ用）
    window.ECM = {
        ExamCountdown,
        ExamList,
        Utils,
        EventSystem,
        Settings,
        Notifications,
        Performance
    };
    
    // AMD/CommonJS対応
    if (typeof define === 'function' && define.amd) {
        define('exam-countdown-manager', [], function() {
            return window.ECM;
        });
    } else if (typeof module !== 'undefined' && module.exports) {
        module.exports = window.ECM;
    }

})(jQuery);