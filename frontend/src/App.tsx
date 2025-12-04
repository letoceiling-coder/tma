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

const AppContent = () => {
  const [isSubscribed, setIsSubscribed] = useState(false);
  const [channelUsernames, setChannelUsernames] = useState<string[]>([]);
  const [loadingChannels, setLoadingChannels] = useState(true);
  const { initUser, isInitializing } = useUserInit();

  // Загружаем список каналов при монтировании
  useEffect(() => {
    const loadChannels = async () => {
      try {
        const apiUrl = import.meta.env.VITE_API_URL || '';
        const apiPath = apiUrl ? `${apiUrl}/api/channels` : `/api/channels`;
        
        const response = await fetch(apiPath, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        });

        if (!response.ok) {
          throw new Error('Ошибка загрузки каналов');
        }

        const data = await response.json();
        const channels = data.channels || [];
        
        if (channels.length === 0) {
          // Если каналов нет, пропускаем проверку подписки
          setIsSubscribed(true);
          
          // Инициализируем пользователя
          try {
            await initUser();
          } catch (error) {
            console.error("Ошибка при инициализации пользователя:", error);
          }
        } else {
          // Загружаем usernames каналов
          setChannelUsernames(channels.map((ch: any) => ch.username));
        }
      } catch (error) {
        console.error('Ошибка загрузки каналов:', error);
        // При ошибке пропускаем проверку подписки
        setIsSubscribed(true);
      } finally {
        setLoadingChannels(false);
      }
    };

    loadChannels();
  }, []);

  const handleSubscribed = async () => {
    setIsSubscribed(true);
    sessionStorage.setItem("channel_subscribed", "true");
    // Сохраняем в localStorage для тестирования
    channelUsernames.forEach((username) => {
      localStorage.setItem(`channel_subscribed_${username}`, "true");
    });

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
