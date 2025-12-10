import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import BottomNav from "@/components/BottomNav";
import ShareIcon from "@/components/ShareIcon";
import { toast } from "sonner";
import leaderboardBunny from "@/assets/leaderboard-bunny-1500.png";
import leaderboardTopText from "@/assets/leaderboard-top-text.png";
import btn5000 from "@/assets/btn-5000.svg";
import { haptic } from "@/lib/haptic";
import useTelegramWebApp from "@/hooks/useTelegramWebApp";

interface LeaderEntry {
  rank: number;
  telegram_id: number;
  username: string;
  avatar_url: string | null;
  invites_count: number;
  prize_amount: number;
}

const Leaderboard = () => {
  const navigate = useNavigate();
  const { userName, share, isReady: tgReady, initData, user } = useTelegramWebApp();
  const [isCopied, setIsCopied] = useState(false);
  const [leaders, setLeaders] = useState<LeaderEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [topPrize, setTopPrize] = useState(1500); // По умолчанию
  const [referralLink, setReferralLink] = useState("https://t.me/wow_roulette_bot");
  const [hasReferrals, setHasReferrals] = useState(false);
  const [currentUser, setCurrentUser] = useState<LeaderEntry | null>(null);
  const [currentUserRank, setCurrentUserRank] = useState<number | null>(null);

  // Загрузка лидерборда и реферальной ссылки с сервера
  useEffect(() => {
    const loadData = async () => {
      try {
        const apiUrl = import.meta.env.VITE_API_URL || '';
        
        // Загружаем лидерборд
        const leaderboardPath = apiUrl ? `${apiUrl}/api/leaderboard` : `/api/leaderboard`;
        const leaderboardHeaders: HeadersInit = {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        };
        
        // Добавляем initData если доступен (требуется для middleware telegram.initdata)
        if (initData) {
          leaderboardHeaders['X-Telegram-Init-Data'] = initData;
        }
        
        const leaderboardResponse = await fetch(leaderboardPath, {
          method: 'GET',
          headers: leaderboardHeaders,
        });

        if (leaderboardResponse.ok) {
          const leaderboardData = await leaderboardResponse.json();
          const leaderboard = leaderboardData.leaderboard || [];
          
          // Применяем визуальные призы для топ-3 (только отображение)
          const leadersWithPrizes = leaderboard.map((leader: LeaderEntry) => {
            let visualPrize = 0;
            if (leader.rank === 1) {
              visualPrize = 1500;
            } else if (leader.rank === 2) {
              visualPrize = 1000;
            } else if (leader.rank === 3) {
              visualPrize = 500;
            }
            return {
              ...leader,
              prize_amount: visualPrize, // Визуальное отображение приза
            };
          });
          
          setLeaders(leadersWithPrizes);
          setHasReferrals(leaderboardData.has_referrals || false);
          
          // Устанавливаем приз за 1 место для баннера
          if (leadersWithPrizes.length > 0) {
            setTopPrize(1500); // Всегда 1500 для первого места
          }

          // Загружаем статистику текущего пользователя для расчета позиции
          if (initData && user?.id) {
            const statsPath = apiUrl ? `${apiUrl}/api/referral/stats` : `/api/referral/stats`;
            const statsResponse = await fetch(statsPath, {
              method: 'GET',
              headers: {
                'X-Telegram-Init-Data': initData,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
            });

            if (statsResponse.ok) {
              const statsData = await statsResponse.json();
              const currentUserInvites = statsData.total_invites || 0;
              
              // Проверяем, есть ли пользователь уже в топе
              const userInTop = leadersWithPrizes.find((leader: LeaderEntry) => leader.telegram_id === user.id);
              
              if (userInTop) {
                // Пользователь уже в топе
                setCurrentUserRank(userInTop.rank);
              } else if (currentUserInvites > 0) {
                // Рассчитываем позицию пользователя на основе количества приглашенных
                // Позиция = количество пользователей с большим количеством приглашений + 1
                let userRank = leadersWithPrizes.length + 1;
                
                // Находим правильную позицию, сравнивая количество приглашенных
                // Если у пользователя больше приглашенных, чем у кого-то в топе, он должен быть выше
                for (let i = 0; i < leadersWithPrizes.length; i++) {
                  if (currentUserInvites >= leadersWithPrizes[i].invites_count) {
                    // У пользователя больше или равно приглашенных - он должен быть на этой позиции или выше
                    userRank = i + 1;
                    break;
                  }
                }
                
                // Если у пользователя больше приглашенных, чем у всех в топе, он на первом месте
                if (leadersWithPrizes.length > 0 && currentUserInvites > leadersWithPrizes[0].invites_count) {
                  userRank = 1;
                }
                
                // Определяем визуальный приз для текущего пользователя (только для топ-3)
                let visualPrize = 0;
                if (userRank === 1) {
                  visualPrize = 1500;
                } else if (userRank === 2) {
                  visualPrize = 1000;
                } else if (userRank === 3) {
                  visualPrize = 500;
                }
                
                setCurrentUser({
                  rank: userRank,
                  telegram_id: user.id,
                  username: user.username || userName || `User ${user.id}`,
                  avatar_url: user.photo_url || null,
                  invites_count: currentUserInvites,
                  prize_amount: visualPrize,
                });
                setCurrentUserRank(userRank);
              }
            }
          }
        }

        // Загружаем реферальную ссылку
        if (initData) {
          const referralPath = apiUrl ? `${apiUrl}/api/referral/link` : `/api/referral/link`;
          const referralResponse = await fetch(referralPath, {
            method: 'GET',
            headers: {
              'X-Telegram-Init-Data': initData,
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
          });

          if (referralResponse.ok) {
            const referralData = await referralResponse.json();
            if (referralData.referral_link) {
              setReferralLink(referralData.referral_link);
            }
          }
        }
      } catch (error) {
        console.error('Ошибка загрузки данных:', error);
        toast.error('Не удалось загрузить данные');
        // Используем fallback ссылку с telegram_id из user, если доступен
        if (user?.id) {
          setReferralLink(`https://t.me/wow_roulette_bot?start=ref${user.id}`);
        }
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, [initData, user]);

  const handleShare = async () => {
    haptic.mediumTap();
    
    try {
      // First copy to clipboard
      await navigator.clipboard.writeText(referralLink);
      
      // Then try Telegram share
      const shared = await share(referralLink, "Присоединяйся к WOW Рулетке! 🎰");
      
      if (shared) {
        haptic.success();
        return;
      }
      
      // Try native share
      if (navigator.share) {
        await navigator.share({
          title: "WOW Рулетка",
          text: "Присоединяйся к WOW Рулетке! Крути колесо и выигрывай призы! 🎰",
          url: referralLink,
        });
        haptic.success();
        return;
      }
      
      // Fallback: already copied, show toast
      haptic.success();
      toast.success("Ссылка скопирована!", { duration: 2000 });
    } catch (error) {
      // Final fallback
      haptic.success();
      toast.success("Ссылка скопирована!", { duration: 2000 });
    }
  };

  const handleInvite = async () => {
    haptic.mediumTap();
    
    try {
      // Try Telegram share first
      const shared = await share(referralLink, "Присоединяйся к WOW Рулетке! 🎰");
      if (shared) {
        haptic.success();
        return;
      }
      
      // Try native share
      if (navigator.share) {
        await navigator.share({
          title: "WOW Рулетка",
          text: "Присоединяйся к WOW Рулетке! Крути колесо и выигрывай призы! 🎰",
          url: referralLink,
        });
        haptic.success();
        return;
      }
      
      // Fallback: copy to clipboard
      await navigator.clipboard.writeText(referralLink);
      setIsCopied(true);
      haptic.success();
      toast.success("Ссылка скопирована!", { duration: 2000 });
      setTimeout(() => setIsCopied(false), 2000);
    } catch (err) {
      console.error("Failed to copy:", err);
    }
  };

  return (
    <div 
      className="relative w-full overflow-hidden"
      style={{ 
        height: '100vh',
        maxHeight: '100vh',
        minHeight: '-webkit-fill-available',
        background: 'linear-gradient(180deg, #FDD4C2 0%, #F8B89A 100%)',
        fontFamily: "'Nunito', -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0
      }}
    >
      {/* Header */}
      <div className="absolute top-3 left-4 flex items-center gap-2">
        <div 
          className="w-8 h-8 rounded-full flex items-center justify-center overflow-hidden"
          style={{ 
            border: '1px solid #F7785B',
            background: '#FFE4D6'
          }}
        >
          <span className="text-lg">🐰</span>
        </div>
        <span 
          className="text-white text-xs font-medium"
          style={{ 
            fontSize: '12px',
            fontFamily: "'Nunito', sans-serif"
          }}
        >
          {userName}
        </span>
      </div>
      
      {/* Invite button and Share icon */}
      <div
        className="absolute flex items-center gap-2"
        style={{
          top: '12px',
          right: '16px',
        }}
      >
        <button
          onClick={handleInvite}
          className="flex items-center justify-center gap-2 transition-all duration-200 active:opacity-80"
          style={{
            height: '36px',
            padding: '0 14px',
            background: 'linear-gradient(135deg, #E88B72 0%, #D87C68 100%)',
            borderRadius: '10px',
            fontSize: '12px',
            color: '#FFFFFF',
            fontWeight: 600,
            border: 'none',
            cursor: 'pointer',
            boxShadow: '0 2px 6px rgba(0,0,0,0.15)',
            fontFamily: "'Nunito', sans-serif"
          }}
        >
          <span>{isCopied ? "Скопировано" : "Пригласить друга"}</span>
          {isCopied ? (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2.5">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          ) : (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
          )}
        </button>
        
        {/* Share Icon Button */}
        <button
          onClick={handleShare}
          className="flex items-center justify-center transition-all duration-200 active:opacity-80"
          style={{
            width: '36px',
            height: '36px',
            background: 'linear-gradient(135deg, #E88B72 0%, #D87C68 100%)',
            borderRadius: '10px',
            border: 'none',
            cursor: 'pointer',
            boxShadow: '0 2px 6px rgba(0,0,0,0.15)',
          }}
          aria-label="Поделиться"
        >
          <ShareIcon size={18} color="#FFFFFF" />
        </button>
      </div>

      {/* Top Banner Card */}
      <div 
        className="absolute left-4 right-4 rounded-2xl overflow-hidden"
        style={{ 
          top: '56px',
          background: 'linear-gradient(135deg, #E19477 0%, #D68B6F 100%)',
          padding: '20px 16px',
          minHeight: '180px',
          boxShadow: '0 4px 16px rgba(0,0,0,0.1)'
        }}
      >
        <div className="flex items-center justify-between h-full relative">
          <div className="flex flex-col gap-2 z-10">
            {/* БУДЬ ПЕРВЫМ В ТОПЕ */}
            <h2 
              style={{
                fontSize: '20px',
                fontWeight: 900,
                color: '#FFE8D9',
                textTransform: 'uppercase',
                lineHeight: 1.2,
                letterSpacing: '0.5px',
                textShadow: '0 2px 4px rgba(0,0,0,0.15)',
                marginBottom: '8px',
                fontFamily: "'Nunito', sans-serif"
              }}
            >
              БУДЬ ПЕРВЫМ<br />В ТОПЕ
            </h2>
            
            {/* Кнопка ПОЛУЧАЙ 5000 ₽ */}
            <div 
              style={{
                background: 'linear-gradient(135deg, #F39C6B 0%, #E88B72 100%)',
                borderRadius: '12px',
                padding: '10px 24px',
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                boxShadow: '0 3px 8px rgba(0,0,0,0.15)',
                width: 'fit-content',
                marginBottom: '6px'
              }}
            >
              <span 
                style={{
                  fontSize: '16px',
                  fontWeight: 700,
                  color: '#FFFFFF',
                  whiteSpace: 'nowrap',
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                ПОЛУЧАЙ {topPrize} ₽
              </span>
            </div>
            
            {/* Подтекст */}
            <p 
              style={{
                fontSize: '12px',
                fontWeight: 400,
                color: 'rgba(255, 255, 255, 0.85)',
                lineHeight: 1.3,
                fontFamily: "'Nunito', sans-serif"
              }}
            >
              подарочной картой<br />каждый месяц
            </p>
          </div>
          
          {/* Заяц */}
          <img 
            src={leaderboardBunny}
            alt="5000₽ Bunny"
            style={{ 
              width: '140px',
              height: 'auto',
              position: 'absolute',
              right: '-5px',
              bottom: '0',
              zIndex: 5
            }}
          />
        </div>
      </div>

      {/* Leaders Title */}
      <h3 
        className="absolute left-0 right-0 text-center"
        style={{
          top: '260px',
          fontSize: '18px',
          fontWeight: 800,
          color: '#FFFFFF',
          textTransform: 'uppercase',
          letterSpacing: '1.5px',
          textShadow: '0 2px 4px rgba(0,0,0,0.1)',
          fontFamily: "'Nunito', sans-serif"
        }}
      >
        ТОП ИГРОКОВ
      </h3>

      {/* Leaders List */}
      <div 
        className="absolute left-4 right-4 overflow-y-auto"
        style={{ 
          top: '295px',
          bottom: '80px',
          paddingBottom: '10px',
          scrollBehavior: 'smooth',
          WebkitOverflowScrolling: 'touch'
        }}
      >
        {loading ? (
          <div className="flex items-center justify-center py-12">
            <p style={{ color: '#FFFFFF', fontSize: '14px', fontFamily: "'Nunito', sans-serif" }}>
              Загрузка...
            </p>
          </div>
        ) : leaders.length === 0 ? (
          <div className="flex items-center justify-center py-12">
            <p style={{ color: '#FFFFFF', fontSize: '14px', fontFamily: "'Nunito', sans-serif" }}>
              {hasReferrals ? 'Топ формируется' : 'Пока нет участников'}
            </p>
          </div>
        ) : (
          leaders.map((leader, index) => (
          <div 
            key={leader.rank}
            className="flex items-center gap-3 mb-3 px-4 py-3 rounded-xl animate-fade-in"
            style={{
              background: 'rgba(255, 230, 215, 0.65)',
              animationDelay: `${index * 0.05}s`,
              backdropFilter: 'blur(8px)',
              boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
            }}
          >
            {/* Avatar with rank */}
            <div className="relative flex-shrink-0">
              <div 
                className="w-12 h-12 rounded-full overflow-hidden"
                style={{ 
                  border: '2px solid #E8A68A',
                  background: leader.avatar_url ? 'transparent' : '#A8D5BA'
                }}
              >
                {leader.avatar_url ? (
                  <img 
                    src={leader.avatar_url} 
                    alt={leader.username}
                    className="w-full h-full object-cover"
                  />
                ) : (
                  <span style={{ fontSize: '24px' }}>👤</span>
                )}
              </div>
              <div 
                className="absolute -bottom-1 -right-1 w-6 h-6 rounded-full flex items-center justify-center"
                style={{
                  background: leader.rank <= 3 ? '#E88B72' : '#D4896E',
                  color: 'white',
                  fontSize: '11px',
                  fontWeight: 700,
                  border: '2px solid white',
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                {leader.rank}
              </div>
            </div>

            {/* Name */}
            <div className="flex-1 min-w-0">
              <span 
                style={{
                  fontSize: '15px',
                  fontWeight: 600,
                  color: '#8B5A47',
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                {leader.username || `User ${leader.telegram_id}`}
              </span>
            </div>

            {/* Referrals */}
            <div className="flex items-center gap-1 flex-shrink-0">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2.5">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span 
                style={{
                  fontSize: '14px',
                  color: '#FFFFFF',
                  fontWeight: 600,
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                {leader.invites_count}
              </span>
            </div>

            {/* Prize - показываем только для топ-3 */}
            {leader.rank <= 3 && leader.prize_amount > 0 && (
              <div 
                className="px-4 py-2 rounded-xl flex-shrink-0"
                style={{
                  background: 'linear-gradient(135deg, #E88B72 0%, #D87C68 100%)',
                  color: 'white',
                  fontSize: '13px',
                  fontWeight: 700,
                  boxShadow: '0 2px 6px rgba(0,0,0,0.15)',
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                {leader.prize_amount} ₽
              </div>
            )}
          </div>
          ))
        )}

        {/* Отображение текущего пользователя, если он не в топ-3 */}
        {currentUser && !leaders.some(leader => leader.telegram_id === currentUser.telegram_id) && (
          <>
            {/* Разделитель */}
            <div 
              style={{
                height: '1px',
                background: 'rgba(255, 255, 255, 0.3)',
                margin: '16px 0',
              }}
            />
            
            {/* Строка текущего пользователя */}
            <div 
              className="flex items-center gap-3 px-4 py-3 rounded-xl"
              style={{
                background: 'rgba(232, 139, 114, 0.85)', // Выделенный фон для текущего пользователя
                backdropFilter: 'blur(8px)',
                boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
                border: '2px solid rgba(255, 255, 255, 0.4)', // Дополнительное выделение
              }}
            >
              {/* Avatar with rank */}
              <div className="relative flex-shrink-0">
                <div 
                  className="w-12 h-12 rounded-full overflow-hidden"
                  style={{ 
                    border: '2px solid #FFFFFF',
                    background: currentUser.avatar_url ? 'transparent' : '#A8D5BA'
                  }}
                >
                  {currentUser.avatar_url ? (
                    <img 
                      src={currentUser.avatar_url} 
                      alt={currentUser.username}
                      className="w-full h-full object-cover"
                    />
                  ) : (
                    <span style={{ fontSize: '24px' }}>👤</span>
                  )}
                </div>
                <div 
                  className="absolute -bottom-1 -right-1 w-6 h-6 rounded-full flex items-center justify-center"
                  style={{
                    background: '#E88B72',
                    color: 'white',
                    fontSize: '11px',
                    fontWeight: 700,
                    border: '2px solid white',
                    fontFamily: "'Nunito', sans-serif"
                  }}
                >
                  {currentUser.rank}
                </div>
              </div>

              {/* Name */}
              <div className="flex-1 min-w-0">
                <span 
                  style={{
                    fontSize: '15px',
                    fontWeight: 600,
                    color: '#FFFFFF',
                    fontFamily: "'Nunito', sans-serif"
                  }}
                >
                  {currentUser.username || `User ${currentUser.telegram_id}`}
                </span>
              </div>

              {/* Referrals */}
              <div className="flex items-center gap-1 flex-shrink-0">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2.5">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span 
                  style={{
                    fontSize: '14px',
                    color: '#FFFFFF',
                    fontWeight: 600,
                    fontFamily: "'Nunito', sans-serif"
                  }}
                >
                  {currentUser.invites_count}
                </span>
              </div>

              {/* Prize - показываем только если пользователь в топ-3 */}
              {currentUser.rank <= 3 && currentUser.prize_amount > 0 && (
                <div 
                  className="px-4 py-2 rounded-xl flex-shrink-0"
                  style={{
                    background: 'linear-gradient(135deg, #FFFFFF 0%, #F5F5F5 100%)',
                    color: '#E88B72',
                    fontSize: '13px',
                    fontWeight: 700,
                    boxShadow: '0 2px 6px rgba(0,0,0,0.15)',
                    fontFamily: "'Nunito', sans-serif"
                  }}
                >
                  {currentUser.prize_amount} ₽
                </div>
              )}
            </div>
          </>
        )}
      </div>

      <BottomNav />
    </div>
  );
};

export default Leaderboard;
