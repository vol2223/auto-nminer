require 'bitfinex-api-rb'
require "date"

fee = 0.0005

now = DateTime.now.strftime("%Y-%m-%d %H:%M:%S") + " "

addr = ENV["NICEHASH_ADDR"]

Bitfinex::Client.configure do |conf|
  conf.api_key = ENV["BFX_API_KEY"]
  conf.secret = ENV["BFX_API_SECRET"]
end

client = Bitfinex::Client.new
balances = client.balances
for wallet in balances do
  if 'exchange' == wallet['type'] and 'zec' == wallet['currency']
    amount = wallet['amount'].to_f
    if 0.0 != amount
      p now + '[I]' + amount.to_s + ' zec sell'
      p client.new_order("ZECBTC", amount, "market", "sell")
    end
  elsif 'exchange' == wallet['type'] and 'btc' == wallet['currency']
    amount = wallet['amount'].to_f
    if 0.0 != amount
      amount = amount - fee;
      p now + '[I]' + amount.to_s + ' btc withdraw'
      p client.withdraw("bitcoin", "exchange", amount, address: addr)
    end
  end
end
