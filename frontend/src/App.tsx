import { useState } from "react";
import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route, useLocation } from "react-router-dom";
import { useEffect } from "react";
import Index from "./pages/Index";
import Friends from "./pages/Friends";
import Leaderboard from "./pages/Leaderboard";
import HowToPlay from "./pages/HowToPlay";
import NotFound from "./pages/NotFound";
import ChannelSubscriptionCheck from "./components/ChannelSubscriptionCheck";
import { useUserInit } from "./hooks/useUserInit";

const queryClient = new QueryClient();

// Scroll to top on route change
const ScrollToTop = () => {
  const { pathname } = useLocation();
  
  useEffect(() => {
    window.scrollTo(0, 0);
    document.querySelectorAll('[data-scroll-container]').forEach((el) => {
      el.scrollTop = 0;
    });
  }, [pathname]);
  
  return null;
};

interface ChannelInfo {
  username: string;
  external_url?: string | null;
  id?: number;
  title?: string;
  priority?: number;
}

const AppContent = () => {
  const [isSubscribed, setIsSubscribed] = useState(false);
  const [channelUsernames, setChannelUsernames] = useState<string[]>([]);
  const [channelsData, setChannelsData] = useState<ChannelInfo[]>([]);
  const [loadingChannels, setLoadingChannels] = useState(true);
  const { initUser, isInitializing } = useUserInit();

  // Загружаем список каналов и проверяем подписку при каждом запуске
  useEffect(() => {
    const loadChannelsAndCheck = async () => {
      try {
        const apiUrl = import.meta.env.VITE_API_URL || '';
        const tg = window.Telegram?.WebApp;
        
        // Загружаем список каналов
        const channelsPath = apiUrl ? `${apiUrl}/api/channels` : `/api/channels`;
        const channelsResponse = await fetch(channelsPath, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        });

        if (!channelsResponse.ok) {
          throw new Error('Ошибка загрузки каналов');
        }

        const channelsData = await channelsResponse.json();
        const channels = channelsData.channels || [];
        
        if (channels.length === 0) {
          // Если каналов нет, пропускаем проверку подписки
          setIsSubscribed(true);
          
          // Инициализируем пользователя
          try {
            await initUser();
          } catch (error) {
            console.error("Ошибка при инициализации пользователя:", error);
          }
          setLoadingChannels(false);
          return;
        }

        // Сохраняем полную информацию о каналах
        const channelsInfo: ChannelInfo[] = channels.map((ch: any) => ({
          id: ch.id,
          username: ch.username,
          external_url: ch.external_url,
          title: ch.title,
          priority: ch.priority,
        }));
        setChannelsData(channelsInfo);
        setChannelUsernames(channels.map((ch: any) => ch.username));

        // ВСЕГДА проверяем подписку через check-all-subscriptions при запуске
        if (tg && tg.initData) {
          const checkPath = apiUrl 
            ? `${apiUrl}/api/check-all-subscriptions?forceCheck=true` 
            : `/api/check-all-subscriptions?forceCheck=true`;
          
          const checkResponse = await fetch(checkPath, {
            method: 'GET',
            headers: {
              'X-Telegram-Init-Data': tg.initData,
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
          });

          if (checkResponse.ok) {
            const checkData = await checkResponse.json();
            // Устанавливаем статус подписки на основе результата проверки
            setIsSubscribed(checkData.all_subscribed === true);
          } else {
            // При ошибке проверки блокируем доступ
            setIsSubscribed(false);
          }
        } else {
          // Если нет initData, блокируем доступ
          setIsSubscribed(false);
        }
      } catch (error) {
        console.error('Ошибка загрузки каналов или проверки подписки:', error);
        // При ошибке блокируем доступ
        setIsSubscribed(false);
      } finally {
        setLoadingChannels(false);
      }
    };

    loadChannelsAndCheck();
  }, []);

  const handleSubscribed = async () => {
    setIsSubscribed(true);
    // НЕ сохраняем в sessionStorage/localStorage - проверка всегда выполняется при запуске

    // Инициализируем пользователя после успешной проверки подписки
    try {
      await initUser();
    } catch (error) {
      console.error("Ошибка при инициализации пользователя:", error);
    }
  };

  // Показываем загрузку пока каналы загружаются
  if (loadingChannels) {
    return (
      <div
        className="fixed inset-0 z-50 flex items-center justify-center"
        style={{
          background: "#FECFB2",
          fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
        }}
      >
        <div className="text-center">
          <p
            style={{
              fontSize: "16px",
              fontWeight: 600,
              color: "#CC5C47",
              margin: 0,
            }}
          >
            Загрузка...
          </p>
        </div>
      </div>
    );
  }

  // Если пользователь не подписан И есть каналы для проверки, показываем экран подписки
  if (!isSubscribed && channelUsernames.length > 0) {
    return (
      <ChannelSubscriptionCheck
        channelUsernames={channelUsernames}
        channels={channelsData}
        onSubscribed={handleSubscribed}
      />
    );
  }

  // Если подписан, показываем основное приложение
  return (
    <BrowserRouter>
      <ScrollToTop />
      <Routes>
        <Route path="/" element={<Index />} />
        <Route path="/friends" element={<Friends />} />
        <Route path="/leaderboard" element={<Leaderboard />} />
        <Route path="/how-to-play" element={<HowToPlay />} />
        {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
        <Route path="*" element={<NotFound />} />
      </Routes>
    </BrowserRouter>
  );
};

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner position="top-center" />
      <AppContent />
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
