#ifndef DROPLOCKER_COMPRESSION_H
#define DROPLOCKER_COMPRESSION_H

#include <string>
#include <vector>
#include <memory>
#include <unordered_map>

namespace DropLocker {

// Compression algorithm types
enum class CompressionType {
    HUFFMAN,
    LZW,
    AUTO  // Automatically choose best algorithm based on file type
};

// Compression result structure
struct CompressionResult {
    bool success;
    size_t originalSize;
    size_t compressedSize;
    double compressionRatio;
    std::string algorithm;
    std::string error;
};

class Compressor {
public:
    // Constructor
    Compressor();
    ~Compressor();

    // Main compression functions
    CompressionResult compress(const std::string& inputFile, 
                              const std::string& outputFile,
                              CompressionType type = CompressionType::AUTO);
    
    CompressionResult decompress(const std::string& inputFile, 
                                const std::string& outputFile);

    // Utility functions
    static CompressionType detectBestAlgorithm(const std::string& filename);
    static std::string getFileExtension(const std::string& filename);
    static double calculateCompressionRatio(size_t original, size_t compressed);

private:
    // Huffman Coding Implementation
    CompressionResult huffmanCompress(const std::string& input, const std::string& output);
    CompressionResult huffmanDecompress(const std::string& input, const std::string& output);
    
    // LZW Implementation  
    CompressionResult lzwCompress(const std::string& input, const std::string& output);
    CompressionResult lzwDecompress(const std::string& input, const std::string& output);
    
    // File I/O helpers
    std::vector<char> readFile(const std::string& filename);
    bool writeFile(const std::string& filename, const std::vector<char>& data);
    size_t getFileSize(const std::string& filename);
};

// Huffman Tree Node
struct HuffmanNode {
    char character;
    int frequency;
    std::shared_ptr<HuffmanNode> left;
    std::shared_ptr<HuffmanNode> right;
    
    HuffmanNode(char c, int freq) : character(c), frequency(freq), left(nullptr), right(nullptr) {}
    HuffmanNode(int freq) : character(0), frequency(freq), left(nullptr), right(nullptr) {}
};

// LZW Dictionary
class LZWDictionary {
public:
    LZWDictionary();
    void initialize();
    int search(const std::string& str);
    void add(const std::string& str);
    std::string getEntry(int code);
    
private:
    std::unordered_map<std::string, int> stringToCode;
    std::unordered_map<int, std::string> codeToString;
    int nextCode;
};

} // namespace DropLocker

#endif // DROPLOCKER_COMPRESSION_H
