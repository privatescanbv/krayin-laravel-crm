using Microsoft.Extensions.Configuration;

namespace UiTests.Support
{
    public static class TestConfig
    {
        private static IConfigurationRoot _config;

        static TestConfig()
        {
            _config = new ConfigurationBuilder()
                .AddJsonFile("appsettings.json")
                .AddEnvironmentVariables()
                .Build();
        }

        public static string BaseUrl => _config["TestSettings:BaseUrl"];
    }
}
